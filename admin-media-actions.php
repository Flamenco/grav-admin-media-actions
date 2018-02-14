<?php
/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2018 TwelveTone LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;

/**
 * Class AdminMediaActionsPlugin
 * @package Grav\Plugin
 */
class AdminMediaActionsPlugin extends Plugin
{

    const ROUTE = '/admin-media-actions';

    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    public function getPath()
    {
        return '/' . trim($this->grav['admin']->base, '/') . '/' . trim(self::ROUTE, '/');
    }

    public function buildBaseUrl()
    {
        $ret = rtrim($this->grav['uri']->rootUrl(false), '/') . '/' . trim($this->getPath(), '/');
        return $ret;
    }

    public function onPluginsInitialized()
    {
        if (!$this->isAdmin() || !$this->grav['user']->authenticated) {
            return;
        }

        // Register the media actions service
        $this->grav['media-actions'] = function ($c) {
            return new MediaActionsController();
        };

        // Ignore requests to the plugin URL
        if ($this->grav['uri']->path() == $this->getPath()) {
            return;
        }

        if ($this->config->get('plugins.admin-media-actions.show_samples')) {
            // Sample Actions
            $this->grav['media-actions']->addAction("SampleAction1", "Sample Action 1", "play-circle", function ($page, $mediaName, $payload) {
                return [
                    "path" => $page->path(),
                    "route" => $page->route(),
                    "mediaName" => $mediaName,
                ];
            });
            $this->grav['media-actions']->addAction("SampleAction2", "Sample Action 2", "play-circle", function ($page, $mediaName, $payload) {
                return [
                    "path" => $page->path(),
                    "route" => $page->route(),
                    "mediaName" => $mediaName,
                ];
            });
            $this->grav['media-actions']->addAction("SampleAction3", "Sample Action 3", "play-circle", function ($page, $mediaName, $payload) {
                return [
                    "path" => $page->path(),
                    "route" => $page->route(),
                    "mediaName" => $mediaName,
                ];
            });

            $this->grav['media-actions']->addAction("SampleForm", "Sample Form", "list", function ($page, $mediaName, $payload) {
                return "ok";
            });
        }


        $this->enable([
            'onAdminTwigTemplatePaths' => ['onAdminTwigTemplatePaths', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onPagesInitialized' => ['onTwigExtensions', 0],
            'onAdminTaskExecute' => ['onAdminTaskExecute', 0],
        ]);
    }

    public function onAdminTaskExecute($e)
    {
        $method = $e['method'];
        switch ($method) {
            case "taskMedia-action":

                $page = $this->grav['admin']->page(false);
                //$route = $page->route();

                $actionId = $_POST['action_id'];
                $media_name = $_POST['media_name'];
                $payload = json_decode($_POST['payload'], true);

                $handler = $this->grav['media-actions']->getHandlerForAction($actionId);
                if ($handler) {
                    $json = $handler($page, $media_name, $payload);
                    die("{\"result\":" . json_encode($json) . "}");
                } else {
                    die("{\"result\":{\"error\":true}}");
                }
                break;
            default:
                return false;
        }
    }

    public function onAdminTwigTemplatePaths()
    {
//        $event['paths'] = __DIR__ . '/themes/grav/templates';
    }

    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigExtensions()
    {
        $page = $this->grav['admin']->page(true);
        if (!$page) {
            return;
        }

        if ($this->config->get('plugins.admin-media-actions.show_samples')) {
            $this->grav['assets']->addJs('plugin://admin-media-actions/assets/samples/sample_actions.js', -1000, false);
            $this->grav['assets']->addJs('plugin://admin-media-actions/assets/samples/sample_form_action.js', -1000, false);
        }

        $oCopy = [];
        foreach ($this->grav['media-actions']->actions as $action) {
            $oCopy[] = [
                'actionId' => $action['actionId'],
                'icon' => $action['icon'],
                'caption' => $action['caption'],
            ];
        }
        $this->grav['assets']->addInlineJs('const MEDIA_ACTIONS = ' . json_encode($oCopy) . ';', -1000, false);

        $taskUrl = $this->buildBaseUrl() . $page->route() . '/task:media-action';
        $this->grav['assets']->addInlineJs('const MEDIA_ACTION_TASK_URL = ' . json_encode($taskUrl) . ';', -1000, false);

        $this->grav['assets']->addJs('plugin://admin-media-actions/assets/admin-media-actions.js', -1000, false);
        $this->grav['assets']->addCss('plugin://admin-media-actions/assets/admin-media-actions.css', -1000, false);
    }

    public function outputError($msg)
    {
        header('HTTP/1.1 400 Bad Request');
        die(json_encode(['error' => ['msg' => $msg]]));
    }

}

class MediaActionsController
{
    public $actions = [];

    /**
     * @param $actionId A unique id for the action.  Must be a valid Javascript function name.
     * This can also be an array containing keys of the same parameter names.
     *
     * @param $caption The caption for the action.  Used for the tooltip.
     * @param $icon The font-awesome icon name.  The 'fa-' prefix is optional.
     * @param $handler A handler for the action. (page, mediaName, payload) => object.
     */
    function addAction($actionId, $caption = null, $icon = null, $handler = null)
    {
        if (is_array($actionId)) {
            if (isset($actionId['caption'])) {
                $caption = $actionId['caption'];
            }
            if (isset($actionId['icon'])) {
                $icon = $actionId['icon'];
            }
            if (isset($actionId['handler'])) {
                $handler = $actionId['handler'];
            }
            // do this last...
            $actionId = $actionId['actionId'];
        }
        $this->actions[$actionId] = [
            'handler' => $handler,
            'caption' => $caption,
            'icon' => $icon,
            'actionId' => $actionId,
        ];
    }

    function getHandlerForAction($actionId)
    {
        if (isset($this->actions[$actionId])) {
            return $this->actions[$actionId]['handler'];
        } else {
            return null;
        }
    }
}
