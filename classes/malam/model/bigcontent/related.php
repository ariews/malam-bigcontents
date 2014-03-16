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
        'contents'      => array('model' => 'bigcontent', 'foreign_key' => 'related_id'),
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

    static function remove_related(Model_Bigcontent $content)
    {
        if (! $content->loaded() || ! $content->related_enable())
        {
            return NULL;
        }

        $content->remove('related_contents');
    }

    static public function update_related(Model_Bigcontent $content, $type = NULL)
    {
        if (! $content->loaded() || ! $content->related_enable())
        {
            return NULL;
        }

        // always refresh
        $content->remove('related_contents');
        $bc = ORM::factory('Bigcontent');

        if (NULL == $type)
        {
            $type = $content->object_name();
        }

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
            $new = ORM::factory('bigcontent_related');

            foreach ($objects as $object)
            {
                $new->clear();

                $new->create_or_update(array(
                    'content_id'    => $content->pk(),
                    'related_id'    => $object->id,
                    'score'         => $object->score,
                ));
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
