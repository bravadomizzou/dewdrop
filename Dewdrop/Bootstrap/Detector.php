<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\Bootstrap;

use ArrayObject;
use Dewdrop\Admin\PageFactory\Custom as CustomPageFactory;
use Dewdrop\Auth\Db\UsersTableGateway;
use Dewdrop\Config;
use Dewdrop\Db\Adapter as DbAdapter;
use Dewdrop\Exception;
use Dewdrop\Paths;
use Pimple;
use Silex\Application;
use Silex\Provider\SessionServiceProvider;
use Symfony\Component\HttpFoundation\Response;
use WP_Session;

/**
 * This class tracks down the bootstrap for your application and grabs
 * the Pimple object from it.  This is used in all the primary execution
 * scripts in your Dewdrop project (i.e. The main PHP file in your doc
 * root for a Silex app, the root plugin file in a WP project, the phpunit
 * bootstrap, and the CLI runner).
 */
class Detector
{
    /**
     * Load the application's bootstrap and retrieve the Pimple DI object
     * from it.  Your Pimple object must provide some basic resources to
     * work with Dewdrop.
     *
     * @return Pimple
     */
    public static function findPimple()
    {
        $config = new Config();

        if (!$config->has('bootstrap')) {
            $bootstrap = new Standalone();
            return $bootstrap->getPimple();
        } else {
            $bootstrapClass = $config->get('bootstrap');

            $bootstrap = new $bootstrapClass();

            if (!$bootstrap instanceof PimpleProviderInterface) {
                throw new Exception('Your bootstrap class must implement the PimpleProviderInterface.');
            }

            $pimple = $bootstrap->getPimple();

            self::validatePimple($pimple);
            self::augmentPimpleWithDefaultResources($pimple);

            return $pimple;
        }
    }

    /**
     * Ensure the Pimple found via the application's bootstrap provides
     * the resources needed for Dewdrop to run properly.  At a minimum,
     * the Pimple should have:
     *
     * 1) A "config" resource that provides an array matching this format:
     *
     * 'config' => [
     *     'db' => [
     *         'type' => 'pgsql' or 'mysql'
     *     ]
     * ]
     *
     * 2) A "db" resources that provides a \Dewdrop\Db\Adapter object.
     *
     * @param Pimple $pimple
     * @return void
     */
    public static function validatePimple(Pimple $pimple)
    {
        if (!isset($pimple['config'])) {
            throw new Exception('Pimple must provide a config resource.');
        } elseif (!isset($pimple['config']['db'])) {
            throw new Exception("Pimple's config resource must contain your db config.");
        } else {
            $dbConfig = $pimple['config']['db'];

            if (!isset($dbConfig['type']) || !in_array($dbConfig['type'], array('pgsql', 'mysql'))) {
                throw new Exception("Pimple's db config must include a type of 'pgsql' or 'mysql'");
            }
        }

        if (!isset($pimple['db']) || !$pimple['db'] instanceof DbAdapter) {
            throw new Exception('Pimple must provide a \Dewdrop\Db\Adapter object in the db resource.');
        }
    }

    /**
     * If the Pimple object doesn't provide definitions for some basic
     * resources, add default definitions for those resources.
     *
     * @param Pimple $pimple
     * @return void
     */
    public static function augmentPimpleWithDefaultResources(Pimple $pimple)
    {
        if (!isset($pimple['debug'])) {
            $pimple['debug'] = false;
        }

        $sharedResources = [
            'dewdrop-request'               => '\Dewdrop\Request',
            'paths'                         => '\Dewdrop\Paths',
            'inflector'                     => '\Dewdrop\Inflector',
            'db.field.input-filter-builder' => '\Dewdrop\Db\Field\InputFilterBuilder'
        ];

        foreach ($sharedResources as $resourceName => $className) {
            if (!isset($pimple[$resourceName])) {
                $pimple[$resourceName] = $pimple->share(
                    function () use ($className) {
                        return new $className();
                    }
                );
            }
        }

        if ($pimple instanceof Application) {
            $pimple->error(
                function (Exception $e) use ($pimple) {
                    if ($pimple['debug']) {
                        return new Response($e->render());
                    }
                }
            );
        }

        if (!isset($pimple['custom-page-factory'])) {
            $pimple['custom-page-factory'] = function () {
                return new CustomPageFactory();
            };
        }

        if (!isset($pimple['dewdrop-build'])) {
            $pimple['dewdrop-build'] = $pimple->share(
                function () use ($pimple) {
                    if (defined('APPLICATION_ENV') && 'development' === APPLICATION_ENV) {
                        return microtime(true);
                    } else {
                        /* @var $paths \Dewdrop\Paths */
                        $paths = $pimple['paths'];

                        return require $paths->getAppRoot() . '/dewdrop-build.php';
                    }
                }
            );
        }

        if (!isset($pimple['session'])) {
            /* @var $paths Paths */
            $paths = $pimple['paths'];
            if ($paths->isWp()) {
                $pimple['session'] = $pimple->share(
                    function () {
                        if (class_exists('WP_Session')) {
                            return WP_Session::get_instance();
                        } else {
                            return new ArrayObject();
                        }
                    }
                );
            } elseif ($pimple instanceof Application) {
                $pimple->register(new SessionServiceProvider());
            } else {
                throw new Exception('Silex application unavailable but not in WordPress');
            }
        }

        if (!isset($pimple['users-gateway'])) {
            $pimple['users-gateway'] = $pimple->share(
                function () use ($pimple) {
                    return new UsersTableGateway($pimple['db']);
                }
            );
        }
    }
}
