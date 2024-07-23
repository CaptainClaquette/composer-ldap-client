<?php

namespace hakuryo\ldap\utils;

use Exception;
use JsonException;
use stdClass;

class ConfigParser
{
    const MANDATORY_KEY = ["HOST", "PWD", "USER", "DN"];

    public static function parseConfigFile($path, $section)
    {
        if (!file_exists($path)) {
            throw new Exception("File $path not found or is not readable");
        }
        if (!is_file($path)) {
            throw new Exception("provided path $path is not a file");
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        switch ($ext) {
            case "json":
                if (mime_content_type($path) === "application/json") {
                    return self::parseJSON($path, $section);
                }
                throw new JsonException("Config file is not a json file or the JSON syntaxe is invalide");
                break;
            case "ini":
                return self::parseINI($path, $section);
                break;
            default:
                throw new Exception("Unsupported config file type must be 'json' or 'ini'");
        }
    }

    public static function parseINI($path, $section): stdClass
    {
        if ($section === null) {
            $raw_conf = parse_ini_file($path);
        } else {
            $raw_conf = parse_ini_file($path, true);
            self::sectionExist($raw_conf, $section);
            $raw_conf = $raw_conf[$section];
        }
        $keys = self::MANDATORY_KEY;

        foreach ($keys as $key) {
            if (array_key_exists($key, $raw_conf)) {
                if ($key === 'DRIVER' && !in_array(strtolower($raw_conf[$key]), ['oci', 'mysql'])) {
                    throw new Exception("Wrong 'DRIVER' key value, acceptable values are 'oci','mysql'");
                }
            } else {
                throw new Exception("You must provide a ini file with the followings keys '" . implode("','", $keys) . "'");
            }
        }
        return self::makeLDAPConfig($raw_conf, $section);
    }

    public static function parseJSON($path, $section): stdClass
    {
        $raw_conf = json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
        if (!$raw_conf) {
            throw new JsonException(json_last_error_msg(), json_last_error());
        }
        if ($section != null) {
            self::sectionExist($raw_conf, $section);
            $raw_conf = $raw_conf->$section;
        }
        $keys = self::MANDATORY_KEY;
        $config = new stdClass();
        foreach ($keys as $key) {
            if (property_exists($raw_conf, $key)) {
                if ($key === 'DRIVER' && !in_array(strtolower($raw_conf->$key), ['oci', 'mysql'])) {
                    throw new Exception("Wrong 'DRIVER' key value, acceptable values are 'oci','mysql'");
                }
            } else {
                throw new Exception("You must provide a json file with the followings keys '" . implode("','", $keys) . "'");
            }
        }
        return self::makeLDAPConfig($raw_conf, $section);
    }

    private static function sectionExist($config, $section)
    {
        if (is_array($config)) {
            if (!array_key_exists($section, $config)) {
                throw new Exception("The provided section '$section' does not exist");
            }
        }
        if (is_object($config)) {
            if (!property_exists($config, $section)) {
                throw new Exception("The provided section '$section' does not exist");
            }
        }
    }

    private static function makeLDAPConfig($raw_conf, $section)
    {
        $raw_conf = (object)$raw_conf;
        $config = new stdClass();
        $config->user = $raw_conf->USER;
        $config->pwd = $raw_conf->PWD;
        $config->base_dn = $raw_conf->DN;
        $config->host = $raw_conf->HOST;
        $config->timeout = property_exists($raw_conf, 'TIMEOUT') ? intval($raw_conf->TIMEOUT) : 3;
        $config->name = property_exists($raw_conf, 'NAME') ? $raw_conf->NAME : $section;
        return $config;
    }
}
