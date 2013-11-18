<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * @author arie
 */

class Malam_HTML extends Kohana_HTML
{
    /**
     * Creates a image link.
     *
     *     echo HTML::image('media/img/logo.png', array('alt' => 'My Company'));
     *
     * @param   string   file name
     * @param   array    default attributes
     * @param   mixed    protocol to pass to URL::base()
     * @param   boolean  include the index page
     * @return  string
     * @uses    URL::base
     * @uses    HTML::attributes
     */
    public static function image($file, array $attributes = NULL, $protocol = NULL, $index = FALSE)
    {
        empty($attributes) && $attributes = array();

        if (strpos($file, '://') === FALSE)
        {
            // Add the base URL
            $file = URL::base($protocol, $index).$file;
        }

        // Add the image link
        $attributes['src'] = $file;

        if (! isset($attributes['alt']))
        {
            $attributes['alt'] = "image: ".basename($file);
        }

        return '<img'.HTML::attributes($attributes).' />';
    }

    public static function update_styles($styles, array $attributes)
    {
        if (empty($attributes))
        {
            return $styles;
        }

        if (! is_array($styles))
        {
            $styles = array_map('trim', explode(';', $styles));
        }

        $tmp = array();
        foreach ($attributes as $key => $value)
        {
            foreach ($styles as $i => $style)
            {
                preg_match('#^(?<key>[a-z-]+)\s*:\s*(?<value>.+)$#i', $style, $match);

                if (isset($match['key']) && $key == $match['key'])
                {
                    $style = "{$key}: {$value}";
                }

                $tmp[] = $style;
                unset($styles[$i]);
            }
        }

        return join('; ', $tmp);
    }

    public static function update_classes($classes, array $attributes)
    {
        if (empty($attributes))
        {
            return $classes;
        }

        if (! is_array($classes))
        {
            $classes = explode(' ', $classes);
        }

        $classes = array_unique(array_merge($classes, $attributes));

        return join(' ', $classes);
    }

    public static function resize_to_style(array $resize, $replace_unit = FALSE, $unit = 'px', $as_array = FALSE)
    {
        if (empty($resize))
        {
            return '';
        }

        $resize = Arr::extract($resize, array('width', 'height'));
        $default_unit = $unit;

        foreach ($resize as $k => $r)
        {
            if (NULL === $r)
            {
                continue;
            }

            preg_match('#^(?P<digit>[0-9.]+)(?<unit>[a-z%]+)?$#i', trim($r), $match);

            if (isset($match['unit']) && TRUE !== $replace_unit)
            {
                $default_unit = $match['unit'];
            }

            $resize[$k] = "{$k}: {$match['digit']}{$default_unit}";
        }

        return (TRUE !== $as_array) ? join('; ', $resize) : $resize;
    }
}