<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * @author arie
 */

class Malam_ORM extends Kohana_ORM
{
    /**
     * Actions without primary_key
     *
     * @var array
     */
    protected $_without_id      = array(
        'index', 'create', 'manage'
    );

    /**
     * Admin route name
     *
     * @var string
     */
    protected $_admin_route_name;

    /**
     * Route name
     *
     * @var string
     */
    protected $_route_name;

    /**
     * Auto set for slug field
     *
     * @var bool
     */
    protected $_auto_slug       = TRUE;

    /**
     * Name field
     *
     * @var string
     */
    protected $_name_field      = NULL;

    /**
     * Admin menu
     *
     * @var array
     */
    protected $_admin_menu      = NULL;

    protected $_menu_prepared   = FALSE;

    protected $_psearch_columns = NULL;

    protected $_ptable_columns  = NULL;

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        empty($this->_ptable_columns)   && $this->_ptable_columns   = array_keys($this->table_columns());
        empty($this->_route_name)       && $this->_route_name       = $this->object_name();
        empty($this->_admin_route_name) && $this->_admin_route_name = "admin-{$this->object_name()}";
    }

    public function Filter_Slug($value)
    {
        $value = trim($value);

        if (! $this->loaded() || TRUE === $this->_auto_slug)
        {
            $this->slug = URL::title($value);
        }

        return $value;
    }

    /**
     * Check for valid status
     *
     * @param string $state
     * @return boolean
     */
    public static function Validation_State($state)
    {
        return in_array($state, array('publish', 'draft', 'pending', 'private'));
    }

    public function Filter_Is_Featured($value)
    {
        return ($value == 'on' || $value == 1);
    }

    protected function link($action = 'index', $title = NULL, array $params = NULL, array $attributes = NULL, array $query = NULL)
    {
        if (NULL === $params)
        {
            $params = array();
        }

        $params += array(
            'id'        => $this->loaded() ? $this->pk() : NULL,
            'action'    => $action,
            'slug'      => in_array('slug', array_keys($this->object()))
                            ? $this->slug : NULL,
            'absolute'  => TRUE,
        );

        $uri_only = FALSE;
        if (isset($params['uri_only']) && $params['uri_only'] == TRUE)
        {
            $uri_only = $params['uri_only'];
            $absolute = $params['absolute'];
            unset($params['uri_only'], $params['absolute']);
        }

        $_route_name = $this->route_name();

        if (preg_match('/^admin[-_](.+)$/i', $action, $matches))
        {
            $_route_name = $this->admin_route_name();
            $params['action'] = $matches[1];
        }

        if (in_array($params['action'], $this->_without_id))
        {
            unset($params['id']);
        }

        $uri = Route::get($_route_name)->uri($params);

        if (! Route::get($_route_name)->matches($uri))
        {
            throw new Kohana_Exception('The requested route does not exist: :route', array(
                ':route'    => $uri
            ));
        }

        if (! empty($query))
        {
            $uri .= URL::query($query);
        }

        if (TRUE === $uri_only)
        {
            return $absolute ? URL::site($uri) : $uri;
        }

        return HTML::anchor($uri, $title, $attributes);
    }

    /**
     * @param string $route
     * @param string $action
     * @param string $title
     * @param array|NULL $params
     * @param array|NULL $attributes
     * @return string
     */
    protected function _url($tmp_route, $action, $title = NULL, array $params = NULL, array $attributes = NULL, array $query = NULL)
    {
        list($tmp, $this->_route_name) = array($this->_route_name, $tmp_route);
        $link = $this->link($action, $title, $params, $attributes, $query);
        $this->_route_name = $tmp;

        return $link;
    }

    public function route_name()
    {
        return $this->_route_name;
    }

    public function admin_route_name()
    {
        return $this->_admin_route_name;
    }

    public function __call($method, $args = array())
    {
        if (preg_match('/^(?<admin_action>(admin[_-])?(?<action>[^_]+))_url(?<uri_only>_only)?$/i', $method, $matches))
        {
            $title  = (isset($args[0]) && NULL !== $args[0])  ? $args[0] : __($matches['action']);
            $params = (isset($args[1]) && is_array($args[1])) ? $args[1] : array();
            $attr   = (isset($args[2]) && is_array($args[2])) ? $args[2] : array();
            $query  = (isset($args[3]) && is_array($args[3])) ? $args[3] : array();

            if (isset($matches['uri_only']))
            {
                $params = array_merge( $params, array('uri_only' => TRUE));
            }

            return $this->link($matches['admin_action'], $title, $params, $attr, $query);
        }
    }

    /**
     * Updates or Creates the record depending on loaded()
     *
     * @chainable
     * @param  Validation $validation Validation object
     * @return ORM
     */
    public function save(Validation $validation = NULL)
    {
        if (! $this->loaded())
        {
            $fields = array_keys($this->object());

            /**
             * mark user as the owner
             */
            $auth = Auth::instance();
            if (in_array('user_id', $fields) && $auth->logged_in())
            {
                $this->user_id = $auth->get_user()->pk();
            }
        }

        return parent::save($validation);
    }

    public function form_url(array $query = NULL)
    {
        $params = array( 'uri_only' => TRUE );
        return $this->link($this->loaded() ? 'admin_update' : 'admin_create', NULL, $params, NULL, $query);
    }

    public function to_paginate()
    {
        return Paginate::factory($this)
            ->columns($this->_ptable_columns)
            ->search_columns($this->_psearch_columns);
    }

    public function get_pagination(array $config = array())
    {
        $request    = Request::current();
        $paginate   = $this->to_paginate();
        $pagination = Pagination::factory($config);
        $start      = 0;
        $search     = FALSE;
        $page       = $request->query('page');
        $query      = $request->query('query');

        if (NULL !== $page && $page >= 1)
        {
            $start  = (($page - 1) * $pagination->items_per_page);
        }

        if (NULL !== $query)
        {
            $paginate->search($query);
            $search = TRUE;
        }

        $paginate->limit($start, $pagination->items_per_page);
        $paginate->execute();

        $pagination->setup(array(
            'total_items' => (TRUE === $search)
                            ? $paginate->count_search_total()
                            : $paginate->count_total()
        ));

        return array(
            'paginate'  => $paginate,
            'pagination'=> $pagination,
        );
    }

    public function find_by_id($id)
    {
        return $this->where($this->primary_key(), '=', $id);
    }

    public function find_by_id_and_slug($id, $slug)
    {
        return $this
            ->where('slug', '=', $slug)
            ->find_by_id($id);
    }

    public function find_by_slug($slug)
    {
        return $this
            ->where('slug', '=', $slug);
    }

    public function find_by_name($name)
    {
        if (NULL === $this->_name_field)
        {
            return $this;
        }

        return $this->where($this->_name_field, '=', $name);
    }

    public static function Get_Or_Create_Tag($data, $model)
    {
        if (! is_array($data))
        {
            $data = trim($data);
            if (empty($data))
            {
                return array();
            }

            $data = explode(',', $data);
        }

        $data   = array_map('trim', $data);
        $ids    = array();

        foreach($data as $d)
        {
            $try = ORM::factory($model)->find_by_name_or_id($d);
            /* @var $try ORM */

            if (! $try->loaded())
            {
                $try = ORM::factory($model)->create_or_update(array($try->name_field() => $d));
            }

            $ids[] = (int) $try->pk();
        }

        return array_unique($ids);
    }

    /**
     * @param mix $nameid String or Integer
     * @return $this
     */
    public function find_by_name_or_id($nameid)
    {
        $nameid = trim($nameid);

        // maybe by id?
        $try    = $this->find_by_id($nameid)->find();

        // or by name?
        if (! $try->loaded())
        {
            $try = $this->find_by_name($nameid)->find();
        }

        // whatever, just return the object
        return $try;
    }

    /**
     * Handles retrieval of all model values, relationships, and metadata.
     *
     * @param   string $column Column name
     * @return  mixed
     */
    public function __get($column)
    {
        if (array_key_exists($column, $this->_object))
        {
            return (in_array($column, $this->_serialize_columns))
                ? $this->_unserialize_value($this->_object[$column])
                : $this->_object[$column];
        }
        elseif (isset($this->_related[$column]))
        {
            // Return related model that has already been fetched
            return $this->_related[$column];
        }
        elseif (isset($this->_belongs_to[$column]))
        {
            $model = $this->_related($column);

            // Use this model's column and foreign model's primary key
            $col = $model->_object_name.'.'.$model->_primary_key;
            $val = $this->_object[$this->_belongs_to[$column]['foreign_key']];

            $model->where($col, '=', $val)->find();

            return $this->_related[$column] = $model;
        }
        elseif (isset($this->_has_one[$column]))
        {
            $model = $this->_related($column);

            // Use this model's primary key value and foreign model's column
            $col = $model->_object_name.'.'.$this->_has_one[$column]['foreign_key'];
            $val = $this->pk();

            if (isset($this->_has_one[$column]['polymorph']) && TRUE == $this->_has_one[$column]['polymorph'])
            {
                $model->where($model->_object_name.'.object_type', '=', $this->_has_one[$column]['type']);
            }

            $model->where($col, '=', $val)->find();

            return $this->_related[$column] = $model;
        }
        elseif (isset($this->_has_many[$column]))
        {
            $model = ORM::factory($this->_has_many[$column]['model']);

            if (isset($this->_has_many[$column]['through']))
            {
                // Grab has_many "through" relationship table
                $through = $this->_has_many[$column]['through'];

                // Join on through model's target foreign key (far_key) and target model's primary key
                $join_col1 = $through.'.'.$this->_has_many[$column]['far_key'];
                $join_col2 = $model->_object_name.'.'.$model->_primary_key;

                $model->join($through)->on($join_col1, '=', $join_col2);

                // Through table's source foreign key (foreign_key) should be this model's primary key
                $col = $through.'.'.$this->_has_many[$column]['foreign_key'];
                $val = $this->pk();
            }
            else
            {
                // Simple has_many relationship, search where target model's foreign key is this model's primary key
                $col = $model->_object_name.'.'.$this->_has_many[$column]['foreign_key'];
                $val = $this->pk();
            }

            if (isset($this->_has_many[$column]['polymorph']) && TRUE == $this->_has_many[$column]['polymorph'])
            {
                $through = isset($this->_has_many[$column]['through'])
                            ? $this->_has_many[$column]['through']
                            : $model->_object_name;

                $model->where($through.'.object_type', '=', $this->_has_many[$column]['type']);
            }

            return $model->where($col, '=', $val);
        }
        else
        {
            throw new Kohana_Exception('The :property property does not exist in the :class class',
                array(':property' => $column, ':class' => get_class($this)));
        }
    }

    /**
     * Adds a new relationship to between this model and another.
     *
     *     // Add the login role using a model instance
     *     $model->add('roles', ORM::factory('role', array('name' => 'login')));
     *     // Add the login role if you know the roles.id is 5
     *     $model->add('roles', 5);
     *     // Add multiple roles (for example, from checkboxes on a form)
     *     $model->add('roles', array(1, 2, 3, 4));
     *
     * @param  string  $alias    Alias of the has_many "through" relationship
     * @param  mixed   $far_keys Related model, primary key, or an array of primary keys
     * @return ORM
     */
    public function add($alias, $far_keys)
    {
        $far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

        $columns = array($this->_has_many[$alias]['foreign_key'], $this->_has_many[$alias]['far_key']);
        $foreign_key = $this->pk();

        $is_polymorph = FALSE;

        if (isset($this->_has_many[$alias]['polymorph']) && TRUE == $this->_has_many[$alias]['polymorph'])
        {
            $is_polymorph = TRUE;
            $columns[]    = 'object_type';
            $type         = $this->_has_many[$alias]['type'];
        }

        $query = DB::insert($this->_has_many[$alias]['through'], $columns);

        foreach ( (array) $far_keys as $key)
        {
            $values = array($foreign_key, $key);

            if ($is_polymorph)
            {
                $values[] = $type;
            }

            $query->values($values);
        }

        $query->execute($this->_db);

        return $this;
    }

    /**
     * Removes a relationship between this model and another.
     *
     *     // Remove a role using a model instance
     *     $model->remove('roles', ORM::factory('role', array('name' => 'login')));
     *     // Remove the role knowing the primary key
     *     $model->remove('roles', 5);
     *     // Remove multiple roles (for example, from checkboxes on a form)
     *     $model->remove('roles', array(1, 2, 3, 4));
     *     // Remove all related roles
     *     $model->remove('roles');
     *
     * @param  string $alias    Alias of the has_many "through" relationship
     * @param  mixed  $far_keys Related model, primary key, or an array of primary keys
     * @return ORM
     */
    public function remove($alias, $far_keys = NULL)
    {
        $far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;

        $query = DB::delete($this->_has_many[$alias]['through'])
            ->where($this->_has_many[$alias]['foreign_key'], '=', $this->pk());

        if ($far_keys !== NULL)
        {
            // Remove all the relationships in the array
            $query->where($this->_has_many[$alias]['far_key'], 'IN', (array) $far_keys);
        }

        if (isset($this->_has_many[$alias]['polymorph']) && TRUE == $this->_has_many[$alias]['polymorph'])
        {
            $query->where('object_type', '=', $this->_has_many[$alias]['type']);
        }

        $query->execute($this->_db);

        return $this;
    }

    static public function Check_Model($id, $model)
    {
        try {
            ! ($model instanceof ORM) && $model = ORM::factory($model);

            if (is_int($id))
            {
                $id = $model->where($model->primary_key(), '=', $id)->find();
                $id = $id->loaded() ? $id->pk() : NULL;
            }

            else if ($id instanceof $model && $id->loaded())
            {
                $id = $id->pk();
            }
        }
        catch (ErrorException $e)
        {
            $id = NULL;
        }

        return $id;
    }

    public function date_fuzzy($field = 'created_at')
    {
        if (in_array($field, array_keys($this->object())))
        {
            return Date::fuzzy_span(strtotime($this->{$field}));
        }

        return NULL;
    }

    protected function _reset_cache()
    {
        Dispatcher::instance()
            ->trigger_event('clear_cache', Dispatcher::event());
    }

    public function create(Validation $validation = NULL)
    {
        $columns = $this->list_columns();

		if (is_array($this->_created_column) && ! isset($columns[$this->_created_column['column']]))
		{
            $this->_created_column = NULL;
		}

        $this->_reset_cache();
        return parent::create($validation);
    }

    public function update(Validation $validation = NULL)
    {
        $columns = $this->list_columns();

		if (is_array($this->_updated_column) && ! isset($columns[$this->_updated_column['column']]))
		{
            $this->_updated_column = NULL;
		}

        $this->_reset_cache();
        return parent::update($validation);
    }

    public function delete()
    {
        $this->_reset_cache();
        return parent::delete();
    }

    public function get_random($limit = 5, $lifetime = Date::DAY)
    {
        $key    = md5($this->object_name()."-random-limit-{$limit}");
        $random = unserialize(Cache::instance()->get($key, serialize(NULL)));

        if (! $random && $limit >= 1)
        {
            $offset = DB::select(DB::expr('FLOOR(RAND() * COUNT(id)) as floor'))
                    ->from($this->table_name())
                    ->where('type', '=', $this->object_name())
                    ->execute()->current();

            $this->limit($limit)->offset($offset['floor']);

            $random = ($limit == 1) ? $this->find() : $this->find_all()->as_array();

            Cache::instance()->set($key, serialize($random), $lifetime);
        }

        return $random;
    }

    public function has_many_relationship_with($alias, $far_keys = NULL, $match_all = FALSE)
    {
        $has_many = $this->_has_many;

        if (isset($has_many[$alias]))
        {
            if (NULL === $far_keys OR TRUE === $match_all)
            {
                return $this->has($alias, $far_keys);
            }

            foreach ($far_keys as $fkey)
            {
                if ($this->has($alias, $fkey))
                {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * Deletes all records
     */
    public function delete_all()
    {
        DB::delete($this->table_name())->execute($this->mdb());
    }

    public function name()
    {
        return (NULL !== $this->_name_field) ? $this->{$this->_name_field} : NULL;
    }

    public function name_field()
    {
        return $this->_name_field;
    }

    public function date($format = 'D, d M Y', $field = 'created_at')
    {
        $time = time();

        if (in_array($field, array_keys($this->object())))
        {
            $time = strtotime($this->{$field});
        }

        return date($format, $time);
    }

    /**
     * Database
     *
     * @return Database
     */
    public function mdb()
    {
        return $this->_db;
    }

    public function table_header()
    {
        $headers  = array();

        foreach ($this->to_paginate()->columns() as $header)
        {
            switch (strtolower($header)):
                case strtolower($this->primary_key()): $text = '#'; break;
                default :  $text = ucfirst(strtolower($header));    break;
            endswitch;

            $headers[$header] = __($text);
        }

        return $headers;
    }

    public function get_field($field)
    {
        switch (strtolower($field)):
            case $this->primary_key():
                return $this->pk();

            case $this->_name_field:
                return $this->name();

            default:
                return $this->$field;
        endswitch;
    }

    public function create_or_update(array $data)
    {
        return $this->values($data)->save();
    }

    public function status_for_selection($name, array $attributes = NULL)
    {
        $statuses = array(
            'publish'   => __('Publish'),
            'draft'     => __('Draft'),
            'pending'   => __('Pending')
        );

        return Form::select($name, $statuses, $this->state, $attributes);
    }

    protected function prepare_menu()
    {}

    public static function capitalize_title($string)
    {
        return ucwords(strtolower(str_replace('_', ' ', $string)));
    }

    public function admin_menu($template = NULL)
    {
        if (FALSE === $this->_menu_prepared)
        {
            $this->prepare_menu();

            if (! empty($this->_admin_menu))
            {
                $theme = Kohana::$config->load('site.ui.admin');
                $menu = Menu::factory($this->_admin_menu)->set_theme($theme);
                $menu->set_attribute('class', 'nav nav-tabs');

                return $menu->render($template);
            }
        }

        return NULL;
    }

    protected function create_menu_key()
    {
        $key    = "ADMIN_MENU_{$this->object_name()}";

        if ($this->loaded())
        {
            $key .= "_{$this->pk()}";
        }

        return $key;
    }

    public function check_if_field_exists($field, $value)
    {
        if (in_array($field, array_keys($this->object())))
        {
            $this->where($field, '=', $value);
        }

        return $this;
    }

    public function featured($bool = TRUE)
    {
        return $this->check_if_field_exists('is_featured', $bool);
    }

    public function publish()
    {
        return $this->check_if_field_exists('state', 'publish');
    }

    public function draft()
    {
        return $this->check_if_field_exists('state', 'draft');
    }

    public function pending()
    {
        return $this->check_if_field_exists('state', 'pending');
    }

    public function active()
    {
        return $this->publish();
    }
}
