<?php

namespace F3CMS;

/**
 * Pagination class for the PHP Fat-Free Framework
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * Copyright (c) 2012 by ikkez
 * Christian Knuth <mail@ikkez.de>
 *
 * @version 1.4.1
 */
class Pagination
{
    /**
     * @var mixed
     */
    private $items_count;
    /**
     * @var mixed
     */
    private $items_per_page;
    /**
     * @var int
     */
    private $range = 2;
    /**
     * @var mixed
     */
    private $current_page;
    /**
     * @var string
     */
    private $template = 'pagebrowser.html';
    /**
     * @var mixed
     */
    private $routeKey;
    /**
     * @var mixed
     */
    private $routeKeyPrefix;
    /**
     * @var mixed
     */
    private $linkPath;

    public const TEXT_MissingItemsAttr = 'You need to specify items attribute for a pagination.';

    /**
     * create new pagination
     *
     * @param $items    array|integer max items or array to count
     * @param $limit    int           max items per page
     * @param $routeKey string        the key for pagination in your routing
     */
    public function __construct($items, $limit = 10, $routeKey = 'page')
    {
        $this->items_count = is_array($items) ? count($items) : $items;
        $this->routeKey    = $routeKey;
        $this->setLimit($limit);
    }

    /**
     * set maximum items shown on one page
     *
     * @param $limit int
     */
    public function setLimit($limit)
    {
        if (is_numeric($limit)) {
            $this->items_per_page = $limit;
        }
        $this->setCurrent(self::findCurrentPage($this->routeKey));
    }

    /**
     * set token name used in your route pattern for pagination
     *
     * @param string $key
     */
    public function setRouteKey($key)
    {
        $this->routeKey = $key;
    }

    /**
     * set a prefix that is added to your page links
     *
     * @param string $prefix
     */
    public function setRouteKeyPrefix($prefix)
    {
        $this->routeKeyPrefix = $prefix;
    }

    /**
     * set path for the template file
     *
     * @param $template string
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * set the range of pages, that are displayed prev and next to current page
     *
     * @param $range int
     */
    public function setRange($range)
    {
        if (is_numeric($range)) {
            $this->range = $range;
        }
    }

    /**
     * set the current page number
     *
     * @param $current int
     */
    public function setCurrent($current)
    {
        if (!$this->routeKeyPrefix) {
            $current = str_replace($this->routeKeyPrefix, '', $current);
        }
        if (!is_numeric($current)) {
            return;
        }

        if ($current <= $this->getMax()) {
            $this->current_page = $current;
        } else {
            $this->current_page = $this->getMax();
        }
    }

    /**
     * set path to current routing for link building
     *
     * @param $linkPath
     */
    public function setLinkPath($linkPath)
    {
        $this->linkPath = ('/' != substr($linkPath, 0, 1)) ? '/' . $linkPath : $linkPath;
        if ('/' != substr($this->linkPath, -1)) {
            $this->linkPath .= '/';
        }
    }

    /**
     * extract the current page number from the route parameter token
     *
     * @param string $key
     *
     * @return int|mixed
     */
    public static function findCurrentPage($key = 'page', $type = 'GET')
    {
        return f3()->exists($type . '.' . $key) ?
        preg_replace('/[^0-9]/', '', f3()->get($type . '.' . $key)) : 1;
    }

    /**
     * returns the current page number
     *
     * @return int
     */
    public function getCurrent()
    {
        return $this->current_page;
    }

    /**
     * returns the maximum count of items to display in pages
     *
     * @return int
     */
    public function getItemCount()
    {
        return $this->items_count;
    }

    /**
     * get maximum pages needed to display all items
     *
     * @return int
     */
    public function getMax()
    {
        return ceil($this->items_count / $this->items_per_page);
    }

    /**
     * get next page number
     *
     * @return int|bool
     */
    public function getNext()
    {
        $nextPage = $this->current_page + 1;
        if ($nextPage > $this->getMax()) {
            return false;
        }

        return $nextPage;
    }

    /**
     * get previous page number
     *
     * @return int|bool
     */
    public function getPrev()
    {
        $prevPage = $this->current_page - 1;
        if ($prevPage < 1) {
            return false;
        }

        return $prevPage;
    }

    /**
     * return last page number, if current page is not in range
     *
     * @return bool|int
     */
    public function getLast()
    {
        return ($this->current_page < $this->getMax() - $this->range) ? $this->getMax() : false;
    }

    /**
     * return first page number, if current page is not in range
     *
     * @return bool|int
     */
    public function getFirst()
    {
        return ($this->current_page > 3) ? 1 : false;
    }

    /**
     * return all page numbers within the given range
     *
     * @param $range int
     *
     * @return array page numbers in range
     */
    public function getInRange($range = null)
    {
        if (is_null($range)) {
            $range = $this->range;
        }

        $current_range = [($this->current_page - $range < 1 ? 1 : $this->current_page - $range),
            ($this->current_page + $range > $this->getMax() ? $this->getMax() : $this->current_page + $range), ];
        $rangeIDs = [];
        for ($x = $current_range[0]; $x <= $current_range[1]; ++$x) {
            $rangeIDs[] = $x;
        }

        return $rangeIDs;
    }

    /**
     * returns the number of items left behind for current page
     *
     * @return int
     */
    public function getItemOffset()
    {
        return ($this->current_page - 1) * $this->items_per_page;
    }

    /**
     * generates the pagination output
     *
     * @return string
     */
    public function serve()
    {
        if (is_null($this->linkPath)) {
            $route = f3()->get('PARAMS.0');
            if (f3()->exists('PARAMS.' . $this->routeKey)) {
                $route = preg_replace('/' . f3()->get('PARAMS.' . $this->routeKey) . '$/', '', $route);
            } elseif ('/' != substr($route, -1));
            $route .= '/';
        } else {
            $route = $this->linkPath;
        }

        f3()->set('pg.route', $route);
        f3()->set('pg.prefix', $this->routeKeyPrefix);
        f3()->set('pg.currentPage', $this->current_page);
        f3()->set('pg.nextPage', $this->getNext());
        f3()->set('pg.prevPage', $this->getPrev());
        f3()->set('pg.firstPage', $this->getFirst());
        f3()->set('pg.lastPage', $this->getLast());
        f3()->set('pg.rangePages', $this->getInRange());
        $output = \Template::instance()->render($this->template);
        f3()->clear('pg');

        return $output;
    }

    /**
     * magic render function for custom tags
     *
     * @static
     *
     * @param $args
     *
     * @return string
     */
    public static function renderTag($args)
    {
        $attr = $args['@attrib'];
        $tmp  = Template::instance();
        foreach ($attr as &$att) {
            $att = $tmp->token($att);
        }

        $pn_code = '$pn = new Pagination(' . $attr['items'] . ');';
        if (array_key_exists('limit', $attr)) {
            $pn_code .= '$pn->setLimit(' . $attr['limit'] . ');';
        }

        if (array_key_exists('range', $attr)) {
            $pn_code .= '$pn->setRange(' . $attr['range'] . ');';
        }

        if (array_key_exists('src', $attr)) {
            $pn_code .= '$pn->setTemplate("' . $attr['src'] . '");';
        }

        if (array_key_exists('token', $attr)) {
            $pn_code .= '$pn->setRouteKey("' . $attr['token'] . '");';
        }

        if (array_key_exists('link-path', $attr)) {
            $pn_code .= '$pn->setLinkPath("' . $attr['link-path'] . '");';
        }

        if (array_key_exists('token-prefix', $attr)) {
            $pn_code .= '$pn->setRouteKeyPrefix("' . $attr['token-prefix'] . '");';
        }

        $pn_code .= 'echo $pn->serve();';

        return '<?php ' . $pn_code . ' ?>';
    }
}
