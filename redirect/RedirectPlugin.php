<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\redirect;

use Herbie;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectPlugin extends Herbie\Plugin
{

    public function onPageLoaded(Herbie\Event $event)
    {
        $page = $event['page'];
        if (!empty($page->redirect)) {
            $url = $this->getUrl($page->redirect);
            $status = $this->getStatus($page->redirect);
            $response = new RedirectResponse($url, $status);
            $response->send();
        }
    }

    /**
     * @param mixed $redirect
     * @return int
     */
    private function getStatus($redirect)
    {
        if (is_array($redirect) && !empty($redirect['status'])) {
            return $redirect['status'];
        }
        return 301;
    }

    /**
     * @param mixed $redirect
     * @return string
     * @throws \Exception
     */
    private function getUrl($redirect)
    {
        if (is_string($redirect)) {
            return $redirect;
        }
        if (is_array($redirect) && !empty($redirect['url'])) {
            return $redirect['url'];
        }
        throw new \Exception('Redirect not properly configued.');
    }
}
