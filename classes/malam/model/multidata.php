<?php

defined('SYSPATH') or die('No direct script access.');

/**
 * Description of multidata
 *
 * @author arie
 */
class Malam_Model_Multidata extends ORM
{
    /**
     * @var bool
     */
    protected $_is_direct_call  = TRUE;

    /**
     * Insert a new object to the database
     *
     * @param  Validation $validation Validation object
     * @return ORM
     */
    public function create(Validation $validation = NULL)
    {
        if ($this->is_direct_call())
        {
            throw new Kohana_Exception('Unable to create data (Directly Call)');
        }

        $this->type = $this->object_name();
        return parent::create($validation);
    }

    /**
     * Initializes the Database Builder to given query type
     *
     * @param  integer $type Type of Database query
     * @return ORM
     */
    protected function _build($type)
    {
        if (! $this->is_direct_call())
        {
            $this->where('type', '=', $this->object_name());
        }

        return parent::_build($type);
    }

    protected function is_direct_call()
    {
        return $this->_is_direct_call;
    }

    public function object_name()
    {
        if (! $this->is_direct_call())
        {
            return parent::object_name();
        }

        return $this->type;
    }
}