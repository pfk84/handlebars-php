<?php
/**
 * This file is part of Handlebars-php
 * Base on mustache-php https://github.com/bobthecow/mustache.php
 *
 * PHP version 5.3
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @author    Behrooz Shabani <everplays@gmail.com>
 * @copyright 2012 (c) ParsPooyesh Co
 * @copyright 2013 (c) Behrooz Shabani
 * @license   MIT <http://opensource.org/licenses/MIT>
 * @version   GIT: $Id$
 * @link      http://xamin.ir
 */

namespace Handlebars;

interface Cache
{

    /**
     * Get cache for $name if exist.
     *
     * @param string $name Cache id
     *
     * @return mixed data on hit, boolean false on cache not found
     */
    public function get($name);

    /**
     * Set a cache
     *
     * @param string $name  cache id
     * @param mixed  $value data to store
     *
     * @return void
     */
    public function set($name, $value);

    /**
     * Remove cache
     *
     * @param string $name Cache id
     *
     * @return void
     */
    public function remove($name);

}