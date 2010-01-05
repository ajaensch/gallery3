<?php defined("SYSPATH") or die("No direct script access.");
/**
 * Gallery - a web based photo album viewer and editor
 * Copyright (C) 2000-2009 Bharat Mediratta
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 */
class tag_rest_Core {
  static function get($request) {
    return rest::reply(rest::resolve($request->url)->as_array());
  }

  static function post($request) {
    $tag = rest::resolve($request->url);

    if (empty($request->params->url)) {
      throw new Rest_Exception("Bad request", 400);
    }

    $item = rest::resolve($request->params->url);

    access::required("edit", $item);
    tag::add($item, $tag->name);

    return rest::reply();
  }

  static function resolve($tag_name) {
    $tag = ORM::factory("tag")->where("name", "=", $tag_name)->find();
    if (!$tag->loaded()) {
      throw new Kohana_404_Exception();
    }

    return $tag;
  }

  // ------------------------------------------------------------

  static function put($request) {
    if (empty($request->arguments[0]) || empty($request->new_name)) {
      throw new Rest_Exception("Bad request", 400);
    }

    $name = $request->arguments[0];

    $tag = ORM::factory("tag")
      ->where("name", "=", $name)
      ->find();
    if (!$tag->loaded()) {
      throw new Kohana_404_Exception();
    }

    $tag->name = $request->new_name;
    $tag->save();

    return rest::reply();
  }

  static function delete($request) {
    if (empty($request->arguments[0])) {
      throw new Rest_Exception("Bad request", 400);
    }
    $tags = explode(",", $request->arguments[0]);
    if (!empty($request->path)) {
      $tag_list = ORM::factory("tag")
        ->join("items_tags", "tags.id", "items_tags.tag_id")
        ->join("items", "items.id", "items_tags.item_id")
        ->where("tags.name", "IN",  $tags)
        ->where("relative_url_cache", "=", $request->path)
        ->viewable()
        ->find_all();
    } else {
      $tag_list = ORM::factory("tag")
        ->where("name", "IN", $tags)
        ->find_all();
    }

    foreach ($tag_list as $row) {
      $row->delete();
    };

    tag::compact();
    return rest::reply();
  }

  private static function _get_items($request) {
    $tags = explode(",", $request->arguments[0]);
    $items = ORM::factory("item")
      ->select_distinct("*")
      ->join("items_tags", "items.id", "items_tags.item_id")
      ->join("tags", "tags.id", "items_tags.tag_id")
      ->where("tags.name", "IN",  $tags);
    if (!empty($request->limit)) {
      $items->limit($request->limit);
    }
    if (!empty($request->offset)) {
      $items->offset($request->offset);
    }
    $resources = array();
    foreach ($items->find_all() as $item) {
      $resources[] = array("type" => $item->type,
                           "has_children" => $item->children_count() > 0,
                           "path" => $item->relative_url(),
                           "thumb_url" => $item->thumb_url(true),
                           "thumb_dimensions" => array("width" => $item->thumb_width,
                                                       "height" => $item->thumb_height),
                           "has_thumb" => $item->has_thumb(),
                           "title" => $item->title);
    }

    return $resources;
  }
}