<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * @author arie
 */

class Malam_Model_Bigcontent extends Model_Multidata
{
    /**
     * Table name
     *
     * @var string
     */
    protected $_table_name      = 'bigcontents';

    /**
     * Auto-update columns for updates
     *
     * @var string
     */
    protected $_updated_column  = array(
        'column'        => 'updated_at',
        'format'        => 'Y-m-d H:i:s'
    );

    /**
     * Auto-update columns for creation
     *
     * @var string
     */
    protected $_created_column  = array(
        'column'        => 'created_at',
        'format'        => 'Y-m-d H:i:s'
    );

    /**
     * @var array
     */
    protected $_sorting         = array(
        'created_at'    => 'DESC'
    );

    /**
     * Auto set for slug field
     *
     * @var bool
     */
    protected $_auto_slug       = TRUE;

    /**
     * Name Field
     *
     * @var string
     */
    protected $_name_field      = 'title';

    /**
     * "Belongs to" relationships
     *
     * @var array
     */
    protected $_belongs_to      = array(
        'user'          => array('model' => 'user', 'foreign_key' => 'user_id'),
    );

    /**
     * Add option 'as feature content' to content
     *
     * @var bool
     */
    protected $_featured_enable = TRUE;

    /**
     * Ability to add many-to-many relationship with tag
     *
     * @var bool
     */
    protected $_tag_enable      = TRUE;

    /**
     * Ability to categorized content
     *
     * @var bool
     */
    protected $_has_hierarchy   = TRUE;

    /**
     * Will hide when deleted
     *
     * @var bool
     */
    protected $_hide_deleted    = FALSE;

    /**
     * Add many-to-many relationship with file
     *
     * @var bool
     */
    protected $_files_enable    = TRUE;

    /**
     * Add many-to-many relationship with image
     *
     * @var bool
     */
    protected $_images_enable   = TRUE;

    /**
     * Add many-to-many relationsip with video
     *
     * @var bool
     */
    protected $_videos_enable   = TRUE;

    /**
     * Add multiple galleries
     *
     * @var bool
     */
    protected $_gallery_enable  = FALSE;

    /**
     * Add many-to-many relationship with gallery
     *
     * @var bool
     */
    protected $_album_enable    = FALSE;

    /**
     * @var bool
     */
    protected $_state_enable    = TRUE;

    protected $_related_enable  = FALSE;

    protected $_psearch_columns = array('title', 'content');

    protected $_ptable_columns  = array('id', 'title', 'creator', 'created at', 'state');

    public function __construct($id = NULL)
    {
        parent::__construct($id);

        if ($this->tag_enable())
        {
            $this->_has_many['tags'] = array(
                'model'         => 'tag',
                'through'       => 'relationship_tags',
                'foreign_key'   => 'object_id',
                'far_key'       => 'tag_id',
                'polymorph'     => TRUE,
                'type'          => $this->object_name()
            );
        }

        if ($this->file_enable())
        {
            $this->_has_many['files'] = array(
                'model'         => 'file',
                'foreign_key'   => 'object_id',
                'far_key'       => 'file_id',
                'through'       => 'relationship_files',
                'polymorph'     => TRUE,
                'type'          => $this->object_name()
            );
        }

        if ($this->image_enable())
        {
            $this->_has_many['images'] = array(
                'model'         => 'file_image',
                'foreign_key'   => 'object_id',
                'far_key'       => 'file_id',
                'through'       => 'relationship_files',
                'polymorph'     => TRUE,
                'type'          => $this->object_name()
            );
        }

        if ($this->video_enable())
        {
            $this->_has_many['videos'] = array(
                'model'         => 'file_video',
                'foreign_key'   => 'object_id',
                'far_key'       => 'file_id',
                'through'       => 'relationship_files',
                'polymorph'     => TRUE,
                'type'          => $this->object_name()
            );
        }

        if ($this->gallery_enable())
        {
            $this->_has_many['galleries'] = array(
                'model'         => 'gallery',
                'foreign_key'   => 'content_id',
            );
        }

        if ($this->album_enable())
        {
            $this->_has_many['albums'] = array(
                'model'         => 'gallery',
                'through'       => 'relationship_galleries',
                'foreign_key'   => 'object_id',
                'far_key'       => 'gallery_id',
                'polymorph'     => TRUE,
                'type'          => $this->object_name(),
            );
        }

        if ($this->hierarchy_enable())
        {
            $this->_belongs_to['category'] = array(
                'model'         => 'category_' . $this->object_name(),
                'foreign_key'   => 'hierarchy_id',
            );
        }

        if ($this->related_enable())
        {
            $this->_has_many['related_contents'] = array(
                'model'         => 'bigcontent',
                'through'       => 'relationship_related',
                'foreign_key'   => 'content_id',
                'far_key'       => 'related_id',
            );
        }
    }

    /**
     * Rule definitions for validation
     *
     * @return array
     */
    public function rules()
    {
        return array(
            'user_id' => array(
                array('not_empty'),
            ),
            'content' => array(
                array('not_empty'),
            ),
            'title' => array(
                array('not_empty'),
                array('max_length', array(':value', 100))
            ),
            'state' => array(
                array(array($this, 'Validation_State'))
            ),
            'type' => array(
                array('not_empty'),
            ),
            'slug' => array(
                array('max_length', array(':value', 100))
            ),
        );
    }

    /**
     * Filter definitions for validation
     *
     * @return array
     */
    public function filters()
    {
        return array(
            'title' => array(
                array('trim'),
                array(array($this, 'Filter_Slug'))
            ),
            'user_id' => array(
                array('ORM::Check_Model', array(':value', 'user'))
            ),
            'content' => array(
                array('trim'),
                array(array($this, 'Filter_Content'))
            ),
            'is_featured' => array(
                array(array($this, 'Filter_Is_Featured'))
            ),
            'hierarchy_id' => array(
                array(array($this, 'Filter_Hierarchy_Id')),
            ),
        );
    }

    public function Filter_Is_Featured($value)
    {
        if ($this->featured_enable())
        {
            return parent::Filter_Is_Featured($value);
        }

        return FALSE;
    }

    public function Filter_Content($value)
    {
        return Markdown(trim($value));
    }

    public function Filter_Hierarchy_Id($value)
    {
        if (! $this->hierarchy_enable())
        {
            return NULL;
        }

        $category = 'category_' . $this->object_name();

        return ORM::Check_Model($value, $category);
    }

    /**
     * Deletes a single record or multiple records, ignoring relationships.
     *
     * @chainable
     * @return ORM
     */
    public function delete()
    {
        if ($this->gallery_enable())
        {
            $gs = $this->galleries->find_all();
            foreach ($gs as $g)
            {
                /* @var $g Model_Gallery */
                $g->delete();
            }
        }

        if (! $this->is_hidden())
        {
            return parent::delete();
        }
        else
        {
            $this->set('is_hidden', TRUE);
            $this->save();

            return $this->clear();
        }
    }

    /**
     * Initializes the Database Builder to given query type
     *
     * @param  integer $type Type of Database query
     * @return ORM
     */
    protected function _build($type)
    {
        if ($this->is_hidden())
        {
            $this->where("{$this->object_name()}.is_hidden", '=', FALSE);
        }

        return parent::_build($type);
    }

    /**
     * Set values from an array with support for one-one relationships.  This method should be used
     * for loading in post data, etc.
     *
     * @param  array $values   Array of column => val
     * @param  array $expected Array of keys to take from $values
     * @return ORM
     */
    public function values(array $values, array $expected = NULL)
    {
        if (NULL === $expected || empty($expected))
        {
            $expected = array('user_id', 'title', 'content', 'state', 'hierarchy_id');

            if ($this->featured_enable())
            {
                $expected[] = 'is_featured';
            }
        }

        return parent::values($values, $expected);
    }

    public function create_or_update(array $data)
    {
        if ($this->tag_enable())
        {
            $tags = ORM::Get_Or_Create_Tag(Arr::get($data, 'join_tags'), 'tag');
        }

        $result = parent::create_or_update($data);

        if ($result->saved() && $this->tag_enable() && ! empty($tags))
        {
            $result->remove('tags');
            $this->add('tags', $tags);
        }

        return $result;
    }

    public function featured_enable($value = NULL)
    {
        if (NULL !== $value)
        {
            $this->_featured_enable = $value;
        }

        return $this->_featured_enable;
    }

    public function tag_enable($value = NULL)
    {
        if (NULL !== $value)
        {
            $this->_tag_enable = $value;
        }

        return $this->_tag_enable;
    }

    public function hierarchy_enable()
    {
        return $this->_has_hierarchy;
    }

    public function related_enable()
    {
        return $this->_related_enable;
    }

    public function file_enable()
    {
        return $this->_files_enable;
    }

    public function image_enable()
    {
        return $this->_images_enable;
    }

    public function video_enable()
    {
        return $this->_videos_enable;
    }

    public function gallery_enable()
    {
        return $this->_gallery_enable;
    }

    public function album_enable()
    {
        return $this->_album_enable;
    }

    public function is_hidden()
    {
        return (TRUE === $this->_hide_deleted);
    }

    public function grab_first_image($resize = NULL, array $attributes = array(), $uri_only = FALSE)
    {
        if ($this->is_direct_call())
        {
            $cnt = ORM::factory($this->type, $this->pk());
            return call_user_func_array(array($cnt, 'grab_first_image'), array($resize, $attributes, $uri_only));
        }

        if (preg_match('#<img[^>]+>#', $this->content, $match))
        {
            if (preg_match('#src=([\'"])([^\'"]+)(\\1)#', $match[0], $rematch))
            {
                $path = $rematch[2];

                // mungkin imagenya hasil upload
                if (preg_match('!^/!i', $rematch[2]))
                {
                    $file   = basename($rematch[2]);
                    $image  = ORM::factory('file_image')->find_by_name($file)->find();
                    /* @var $image Model_File_Image */

                    if ($image->loaded() && NULL !== $resize)
                    {
                        $path = $image->thumbnail_with_size($resize['width'], $resize['height']);
                    }
                }

                $path = trim($path, '/');

                if (TRUE === $uri_only)
                {
                    return $path;
                }

                if (NULL !== $resize)
                {
                    ! is_array($resize) && $resize = array($resize);

                    $styles = Arr::get($attributes, 'style');
                    $resize = HTML::resize_to_style($resize, TRUE, 'px', TRUE);

                    if (NULL !== $styles)
                    {
                        $styles = HTML::update_styles($styles, $resize);
                    } else {
                        $styles = implode('; ', $resize);
                    }

                    $attributes['style'] = $styles;
                }

                return HTML::image($path, $attributes);
            }
        }

        return NULL;
    }

    public function __call($method, $args = array())
    {
        if (preg_match('/^grab_first_image_and_resize_to_(?P<width>\d+)(x(?P<height>\d+))?$/i', $method, $matches))
        {
            $resize     = Arr::extract($matches, array('width', 'height'));
            $attributes = (isset($args[0]) && is_array($args[0])) ? $args[0] : array();

            return $this->grab_first_image($resize, $attributes);
        }
        else
        {
            if ($this->is_direct_call() &&
                preg_match('/^(?<admin_action>(admin[_-])?(?<action>[^_]+))_url(?<uri_only>_only)?$/i', $method, $matches)
            )
            {
                $cnt = ORM::factory($this->type, $this->pk());
                return call_user_func_array(array($cnt, $method), $args);
            }
        }
        return parent::__call($method, $args);
    }

    public function content_as_featured_text($limit = 50, $field = 'content')
    {
        $contents = preg_replace('#<\/?(em|pre|img|a|p|b|small|i|ul|ol|li|strong|span|div|dt|dd|dl)([^>]+)?>#i',
                '', Markdown($this->$field));

        $contents = array_map('trim', explode("\n", $contents));

        if ($contents)
        {
            foreach ($contents as $match)
            {
                if (!empty($match) || "" != $match)
                {
                    return Markdown(Text::limit_words($match, $limit));
                }
            }
        }
    }

    public function to_paginate()
    {
        return parent::to_paginate()->sort('created_at', Paginate::SORT_DESC);
    }

    public function get_field($field)
    {
        switch (strtolower($field)):
            case 'creator':
                $user   = Auth::instance()->get_user();
                return $this->user->name();

            case 'created_at':
            case 'created at':
                return parent::get_field('created_at');

            case 'title':
                return $this->admin_update_url($this->name());

            case 'content':
                return $this->content_as_featured_text();

            default :
                return parent::get_field($field);
        endswitch;
    }

    public function posted_at($format = 'F d, Y')
    {
        // safe
        $_date = strtotime($this->created_at);
        return date($format, $_date);
    }

    public function previous_post($text = NULL, array $params = NULL, array $attributes = NULL)
    {
        return $this->navi('created_at', '<', $this->created_at, $text, 'DESC', $params, $attributes);
    }

    public function next_post($text = NULL, array $params = NULL, array $attributes = NULL)
    {
        return $this->navi('created_at', '>', $this->created_at, $text, 'ASC', $params, $attributes);
    }

    private function navi($field, $operator, $value, $text = NULL, $order = NULL, array $params = NULL, array $attributes = NULL)
    {
        $r = ORM::factory($this->object_name())
                ->where($field, $operator, $value);

        if (NULL !== $order)
            $r->order_by($field, $order);

        $r = $r->find();

        if ($r->loaded())
        {
            if (NULL === $text)
                $text = $r->title;

            return $r->read_url($text, $params, $attributes);
        }

        return FALSE;
    }

    /**
     * Create last modified date (GMT)
     */
    public function last_modified()
    {
        $time = $this->{$this->_created_column['column']};
        if ($this->{$this->_updated_column['column']})
        {
            $time = $this->{$this->_updated_column['column']};
        }

        return gmdate('D, d M Y H:i:s', strtotime($time)) . ' GMT';
    }

    public function object_name()
    {
        if (! $this->is_direct_call())
        {
            return parent::object_name();
        }
        else
        {
            return $this->loaded() ? $this->type : $this->_object_name;
        }
    }

    protected function prepare_menu()
    {
        $menu = array(
            array(
                'title' => __(ORM::capitalize_title($this->object_name())),
                'url'   => $this->admin_index_url_only(),
            ),
            array(
                'title' => __($this->loaded() ? 'Update' : 'Add'),
                'url'   => $this->loaded()
                            ? $this->admin_update_url_only()
                            : $this->admin_create_url_only()
            ),
        );

        if ($this->loaded() && $this->image_enable())
        {
            $menu[] = array(
                'title' => __('Images'),
                'url'   => $this->images
                                ->set_content($this)
                                ->gallery_index_url_only(),
            );
        }

        if ($this->hierarchy_enable())
        {
            $menu[] = array(
                'title' => __('Category'),
                'url'   => $this->category->admin_index_url_only(),
            );
        }

        $this->_admin_menu = $menu;
    }

    public function __get($column)
    {
        $return = parent::__get($column);

        if ($this->hierarchy_enable() && $column = 'category')
        {
            if ($return instanceof Model_Hierarchy)
            {
                /* @var $return Model_Hierarchy */
                $return->set_content($this);
            }
        }

        if ($this->gallery_enable() && $column = 'galleries')
        {
            if ($return instanceof Model_Gallery)
            {
                /* @var $return Model_Gallery */
                $return->set_content($this);
            }
        }

        return $return;
    }

    public function total_photos()
    {
        if (! $this->image_enable())
            return NULL;

        return $this->images->find_all()->count();
    }
}