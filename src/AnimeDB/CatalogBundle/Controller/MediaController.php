<?php
/**
 * AnimeDB package
 *
 * @package   AnimeDB
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDB\CatalogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Favicon
 *
 * @package AnimeDB\CatalogBundle\Controller
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class MediaController extends Controller
{
    /**
     * Show icon
     *
     * @param string $host
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function faviconAction($host)
    {
        $path = realpath(__DIR__.'/../../../../web').'/media/favicon/';
        $file = $path.$host.'.ico';
        if (!file_exists($file)) {
            $fs = new Filesystem();
            $fs->copy('http://'.$host.'/favicon.ico', $file);
            // not found
            if (file_exists($file) && !file_get_contents($file)) {
                $fs->remove($file);
                return new Response('', 404, ['Content-Type' => 'image/x-icon']);
            }
        }
        return new Response(file_get_contents($file), 200, ['Content-Type' => 'image/x-icon']);
    }
}