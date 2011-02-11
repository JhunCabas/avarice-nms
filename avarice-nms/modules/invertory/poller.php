<?php

/*
 +--------------------------------------------------------------------------+
 | Copyright (C) 2009-2010 Xinn.org                                         |
 |                                                                          |
 | This program is free software; you can redistribute it and/or            |
 | modify it under the terms of the GNU General Public License              |
 | as published by the Free Software Foundation; either version 2           |
 | of the License, or (at your option) any later version.                   |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
 | GNU General Public License for more details.                             |
 +--------------------------------------------------------------------------+
 |Avarice-nms:A greedy and insatiable inventory and network managment system|
 +--------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Xinn.org. See      |
 | about.php and/or the AUTHORS file for specific developer information.    |
 +--------------------------------------------------------------------------+
 | http://avarice-nms.com                                                   |
 | http://avarice-nms.info                                                  |
 | http://xinn.org/avarice.php                                              |
 +--------------------------------------------------------------------------+
*/

// Function to create xml from results array
function resultxml ($array, $pad = 1) {
  $return = "";
  foreach ($array as $key => $value) {
    if (is_array($value)) {
      if (is_numeric($key)) {
        $key = "instance";
      };
      $return .= str_repeat(" ", $pad) . "<" . $key . ">\r\n";
      $pad++;
      $return .= resultxml($value, $pad);
      $pad--;
      $return .= str_repeat(" ", $pad) . "</" . $key . ">\r\n";
    } else {
      $return .= str_repeat(" ", $pad) . "<" . $key . ">" . $value . "</" . $key . ">\r\n";
    };
  };
  return $return;
};

$self = array("ScanStarted" => date('Y-m-d H:i:s', microtime(true)));

include_once("../../include/config.php");

// Used to determine template to run.  Needs to be fleshed out. Currently 
// defaulting to template ID 1
$os_details = array("os"      => php_uname('s'),
                    "release" => php_uname('r'),
                    "version" => php_uname('v'));
$query = "
          SELECT template
            FROM inv__config_templates
           WHERE templateID = 1";
$template         = mysql_result(dbquery_func($avarice_user_connection, $query), 0, 0);
$template_xml     = simplexml_load_string($template);
$return_xml_array = array("asset" => array());
$wmi_information  = array();
foreach ($template_xml->asset->property as $property) {
  if ($property['method'] == "wmi") {
    if (!in_array((string)$property['namespace'], array_keys($wmi_information))) {
      $wmi_information[(string)$property['namespace']] = array("props" => array());
    };
    $wmi_information[(string)$property['namespace']]['props'][] = (string)$property['property'];
  };
};
foreach ($template_xml->asset->category as $category) {
  if ($category['type'] == "single") {
    foreach ($category->property as $property) {
      if ($property['method'] == "wmi") {
        if (!in_array((string)$property['namespace'], array_keys($wmi_information))) {
          $wmi_information[(string)$property['namespace']] = array("props" => array());
        };
        $wmi_information[(string)$property['namespace']]['props'][] = (string)$property['property'];
      };
    };
  } else if ($category['type'] = "multiple") {
    foreach ($category->instance->property as $property) {
      if ($property['method'] == "wmi") {
        if (!in_array((string)$property['namespace'], array_keys($wmi_information))) {
          $wmi_information[(string)$property['namespace']] = array("props" => array());
        };
        $wmi_information[(string)$property['namespace']]['props'][] = (string)$property['property'];
      };
    };
  };
};

foreach($wmi_information as $key => $value) {
  $obj = new COM('winmgmts:{impersonationLevel=impersonate}//./root/cimv2');
  $query = "SELECT * FROM " . $key;
  $result = $obj->ExecQuery($query);
  $wmi_information[$key]['result'] = array();
  foreach ($result as $resvalue) {
    $resarray = array();
    foreach ($value['props'] as $prop) {
      $resarray[$prop] = (string)$resvalue->$prop;
    };
    $wmi_information[$key]['result'][] = $resarray;
  };
};

$self['ScanEnded'] = date('Y-m-d H:i:s', microtime(true));

foreach ($template_xml->asset->property as $property) {
  if ($property['method'] == "wmi") {
    $return_xml_array['asset'][(string)$property['name']] = $wmi_information[(string)$property['namespace']]['result'][0][(string)$property['property']];
  } else if ($property['method'] == "self") {
    $return_xml_array['asset'][(string)$property['name']] = $self[(string)$property['name']];
  };
};
foreach ($template_xml->asset->category as $category) {
  $return_xml_array['asset'][(string)$category['name']] = array();
  if ($category['type'] == "single") {
    foreach ($category->property as $property) {
      if ($property['method'] == "wmi") {
        $return_xml_array['asset'][(string)$category['name']][(string)$property['name']] = $wmi_information[(string)$property['namespace']]['result'][0][(string)$property['property']];
      };
    };
  } else if ($category['type'] = "multiple") {
    foreach ($category->instance->property as $property) {
      if ($property['method'] == "wmi") {
        for ($x = 0; $x < count($wmi_information[(string)$property['namespace']]['result']); $x++) {
          if (!isset($return_xml_array['asset'][(string)$category['name']][$x])) {
            $return_xml_array['asset'][(string)$category['name']][$x] = array();
          };
          $return_xml_array['asset'][(string)$category['name']][$x][(string)$property['name']] = $wmi_information[(string)$property['namespace']]['result'][$x][(string)$property['property']];
        };
      };
    };
  };
};

$query = "
          SELECT value
            FROM inv__config_misc
           WHERE parameter = 'xml_path'";
$xml_dir = mysql_result(dbquery_func($avarice_user_connection, $query), 0, 0);
$filename = $xml_dir . "\\" . date('YmdHis') . $_SERVER['COMPUTERNAME'] . ".xml";
$data     = "<inventory>\r\n" . resultxml($return_xml_array) . "</inventory>";
file_put_contents($filename, $data);

?>