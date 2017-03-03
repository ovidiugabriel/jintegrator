<?php

/* ************************************************************************* */
/*                                                                           */
/*  Title:       integrate.php                                               */
/*                                                                           */
/*  Created on:  03.03.2017 at 20:09:23                                      */
/*  Email:       ovidiugabriel@gmail.com                                     */
/*  Copyright:   (C) 2015 ICE Control srl. All Rights Reserved.              */
/*                                                                           */
/*  $Id$                                                                     */
/*                                                                           */
/* ************************************************************************* */
/*
 * Copyright (c) 2015, ICE Control srl.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);

define ('JHOME', realpath(dirname(__FILE__)  . '/..'));

// The path to the installed Joomla! site,
// or JPATH_ROOT/administrator if executed from the backend.
define ('JPATH_BASE',          null);

// The path to the Joomla Platform (where loader.php or platform.php is located,
// usually in the same folder as import.php).
define ('JPATH_PLATFORM',      JHOME . '/libraries');

// The path to folder containing the configuration.php file.
define ('JPATH_CONFIGURATION', JHOME);

require_once JPATH_CONFIGURATION . '/configuration.php';

jml_import('loader');
jml_import('vendor.joomla.registry.src.Registry');
jml_import('joomla.database.exception.unsupported');
jml_import('joomla.database.exception.connecting');
jml_import('joomla.database.exception.executing');
jml_import('joomla.database.interface');
jml_import('joomla.database.database');
jml_import('joomla.database.driver');
jml_import('joomla.database.query');
jml_import('joomla.database.query.limitable');
jml_import('joomla.database.query.element');
jml_import('joomla.database.query.mysqli');
jml_import('joomla.database.driver.mysqli');
jml_import('joomla.date.date');
jml_import('joomla.log.log');
jml_import('joomla.log.entry');
jml_import('joomla.language.text');
jml_import('joomla.language.language');
jml_import('joomla.factory');


class Settings {
    public $component_name;
    public $controller_name;
    public $controller_value;

    public function __construct($filename) {
        if (! file_exists($filename)) {
            throw new Exception("$filename - no such input file");
        }

        $config = parse_ini_file($filename);
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
    }

    public function toArray() {
        return (array) $this;
    }
}

/**
 *
 * @param  string $name
 * @return void
 */
function jml_import($name) {
    $file  =  JPATH_PLATFORM . '/' . str_replace('.', '/', $name) . '.php';
    require_once $file;
}

/**
 * @return JDatabaseDriver
 */
function database() {
    static $db = null;

    if (null == $db) {
        // Creates a database connection using Joomla driver
        $jconfig = new JConfig();

        $option = array();
        $option['driver']   = $jconfig->dbtype;;            // Database driver name
        $option['host']     = $jconfig->host;               // Database host name
        $option['user']     = $jconfig->user;               // User for database authentication
        $option['password'] = $jconfig->password;           // Password for database authentication
        $option['database'] = $jconfig->db;                 // Database name
        $option['prefix']   = $jconfig->dbprefix;           // Database prefix (may be empty)

        $db = JDatabaseDriver::getInstance( $option );
    }
    return $db;
}

/**
 *
 * @param  string $text
 * @param  mixed $default [description]
 * @return mixed
 */
function cli_prompt($text, $default = null) {
    echo $text;
    if ($default) {
        echo "[default=$default]";
    }
    echo ': ';
    $val = trim(fgets(STDIN));
    if (!$val && $default) {
        return $default;
    }
    return $val;
}

/**
 *
 * @param  string $component_name
 * @return array
 */
function get_extension($component_name) {
    $db = database();
    $db->setQuery(sprintf("SELECT * FROM %s WHERE `name` = %s",
        $db->quoteName('#__extensions'), $db->quote($component_name)));
    return $db->loadAssoc();
}

/**
 * @param string $table
 * @param array $data
 * @return void
 */
function db_insert($table, array $data) {
    $db = database();

    // Create a new query object.
    $query = $db->getQuery(true);

    // Insert columns.
    $columns = array_keys($data);

    // Insert values.
    $values = array_map( array($db, 'quote'), array_values($data) );

    // Prepare the insert query.
    $query->insert($db->quoteName($db->replacePrefix($table)))
        ->columns($db->quoteName($columns))
        ->values(implode(',', $values));

    // Set the query using our newly populated query object and execute it.
    $db->setQuery($query);
    $db->execute();
}

/**
 *
 * @param  string $table
 * @param  array  $data
 * @param  string $field
 * @param  mixed $value
 * @return void
 */
function db_update($table, array $data, $field, $value) {
    $db = database();

    // Fields to update.
    $fields = array();
    foreach ($data as $key => $value) {
        $fields[] = $key . ' = ' . $db->quote($value);
    }

    // Conditions for which records should be updated.
    $conditions = array( $db->quoteName($field) . ' = ' . $db->quote($value) );

    $query = $db->getQuery(true);
    $query->update($db->quoteName('#__menu'))->set($fields)->where($conditions);

    $db->setQuery($query);

    $db->execute();
}

/**
 *
 * @param  Settings $settings
 * @return string
 */
function build_link(Settings $settings) {
    return 'index.php?' . http_build_query(array(
            'option'                    => $settings->component_name,
            $settings->controller_name  => $settings->controller_value,
        ));
}

//
// Main code
//


$settings = new Settings('config.ini');
$link = build_link($settings);

$properties = get_extension($settings->component_name);

$data = array(
    'menutype'     => 'menu',
    'title'        => $settings->component_title,
    'alias'        => $settings->component_title,
    'path'         => $settings->component_name,
    'link'         => $link,
    'type'         => 'component',
    'published'    => 1,
    'parent_id'    => 1,
    'level'        => 0,
    'component_id' => (int) $properties['extension_id'],
    'access'       => 3,
    'language'     => '*',
    'client_id'    => $properties['client_id'],
);

try {
    db_insert('#__menu', $data);
} catch (JDatabaseExceptionExecuting $ex) {
    if (1062 == $ex->getCode()) {  // TODO: Used typed exception for 1062
        // already exists
        db_update('#__menu', $data, 'component_id', (int) $properties['extension_id']);
    }
}

// EOF
