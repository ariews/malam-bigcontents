<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * @author arie
 */

class Malam_Model_Bigcontent_Related extends ORM
{
    /**
     * Table name
     *
     * @var string
     */
    protected $_table_name      = 'relationship_related';

    /**
     * Auto-update columns for updates
     *
     * @var string
     */
    protected $_updated_column  = array(
        'column'        => 'created_at',
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

    protected $_has_many        = array(
        'contents'      => array('model' => 'bigcontent', 'foreign_key' => 'object_id'),
    );

    protected $_belongs_to      = array(
        'reference'     => array('model' => 'bigcontent', 'foreign_key' => 'content_id'),
    );

    /**
     * @var array
     */
    protected $_sorting         = array(
        'score'         => 'DESC'
    );

    /**
     * Rule definitions for validation
     *
     * @return array
     */
    public function rules()
    {
        return array(
            'content_id' => array(
                array('not_empty'),
            ),
            'object_id' => array(
                array('not_empty'),
            ),
            'object_type' => array(
                array('not_empty'),
            ),
            'score' => array(
                array('not_empty'),
            ),
        );
    }

    static function create_type(Model_Bigcontent $content, $type = NULL)
    {
        return NULL === $type ? $content->object_name() : $type;
    }

    static function has_related(Model_Bigcontent $content, $type)
    {
        if (! $content->loaded() || ! $content->related_enable())
        {
            return FALSE;
        }

        $type = self::create_type($content, $type);

        return ORM::factory('bigcontent_related')
                ->where('object_type', '=', $type)
                ->where('content_id', '=', $content->pk())->count_all();
    }

    static function get_related(Model_Bigcontent $content, $type = NULL, $limit = 20)
    {
        if (! $content->loaded() || ! $content->related_enable())
        {
            return NULL;
        }
        $type = self::create_type($content, $type);
        $rel  = ORM::factory('bigcontent_related');
        $bc   = ORM::factory('bigcontent');

        return ORM::factory($bc->object_name())
            ->join(array($rel->table_name(), $rel->object_name()))
            ->on("{$rel->object_name()}.object_id", '=', "{$bc->object_name()}.{$bc->primary_key()}")
            ->where("{$rel->object_name()}.content_id", '=', $content->pk())
            ->where("{$rel->object_name()}.object_type", '=', $type)
            ->limit($limit)
            ->find_all();
    }

    static function remove_related(Model_Bigcontent $content, $type = NULL)
    {
        if ($content->loaded() && $content->related_enable())
        {
            $rel  = ORM::factory('Bigcontent_Related');
            $type = self::create_type($content, $type);

            DB::delete($rel->table_name())
                    ->where('content_id', '=', $content->pk())
                    ->where('object_type', '=', $type)
                    ->execute($rel->mdb());
        }
    }

    static public function update_related(Model_Bigcontent $content, $type = NULL)
    {
        if (! $content->loaded() || ! $content->related_enable())
        {
            return;
        }

        $type = self::create_type($content, $type);
        $bc   = ORM::factory('Bigcontent');

        $ckeyword = self::get_keywords($content->content);
        $tkeyword = self::get_keywords($content->name());

        $objects = DB::select($bc->primary_key())
                ->select(array(
                    DB::expr("ROUND(0 + (MATCH ({$bc->object_name()}.content) AGAINST ('{$ckeyword}')) * 1 + (MATCH ({$bc->object_name()}.title) AGAINST ('{$tkeyword}')) * 1,1)")
                    , 'score'))
                ->from(array($bc->table_name(), $bc->object_name()))
                ->where("{$bc->object_name()}.{$bc->primary_key()}", '!=', $content->pk())
                ->where("{$bc->object_name()}.type", '=', $type)
                ->where("{$bc->object_name()}.state", '=', 'publish')
                ->group_by("{$bc->object_name()}.{$bc->primary_key()}")
                ->having('score', '>=', 5.0)
                ->order_by('score', 'DESC')
                ->limit(Kohana::$config->load('bigcontent.related.limit'))
                ->execute($bc->mdb(), TRUE);

        if ($objects->count())
        {
            self::remove_related($content, $type);

            foreach ($objects as $object)
            {
                $new = ORM::factory('bigcontent_related');
                $data = array(
                    'content_id'    => $content->pk(),
                    'object_id'     => $object->id,
                    'object_type'   => $type,
                    'score'         => $object->score,
                );

                $new->create_or_update($data);
            }
        }
    }

    static public function get_keywords($string)
    {
        $overusedwords  = Kohana::$config->load('bigcontent.related.overusedwords');
        $softhyphen     = html_entity_decode('&#173;',ENT_NOQUOTES,'UTF-8');
        $text           = str_replace($softhyphen, '',
                            preg_replace('/&(#x[0-9a-f]+|#[0-9]+|[a-zA-Z]+);/',
                                '',
                                strip_tags(Markdown($string))
                            ));

        if (function_exists('mb_split'))
        {
            mb_regex_encoding('utf8');
            $wordlist = mb_split('\s*\W+\s*', mb_strtolower($text, 'utf8'));
        } else {
            $wordlist = preg_split('%\s*\W+\s*%', strtolower($text));
        }

        $tokens = array_count_values($wordlist);

        if (is_array($overusedwords))
        {
            foreach ($overusedwords as $word)
            {
                unset($tokens[$word]);
            }
        }

        // Remove words which are only a letter
        foreach (array_keys($tokens) as $word)
        {
            if (function_exists('mb_strlen') && mb_strlen($word) < 2)
            {
                unset($tokens[$word]);
            }
            elseif (strlen($word) < 2)
            {
                unset($tokens[$word]);
            }
        }

        arsort($tokens, SORT_NUMERIC);
        $types = array_slice(array_keys($tokens), 0, 20);

        return implode(' ', $types);
    }
}
