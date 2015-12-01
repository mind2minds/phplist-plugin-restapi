<?php

namespace phpListRestapi;

defined('PHPLISTINIT') || die;

class Common
{
    public static function select($type, $sql, $params = array(), $single = false)
    {
       $response = new Response();
       try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            foreach ($params as $param => $paramValue) {
                $stmt->bindParam($param, $paramValue[0],$paramValue[1]);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;
            if ($single && is_array($result) && isset($result[0])) {
                $result = $result[0];
            }
            $response->setData($type, $result);
        } catch (\Exception $e) {
            $response->setError($e->getCode(), $e->getMessage());
        }
        $response->output();
    }

    public static function logRequest($cmd)
    {
       $response = new Response();
       $requestData = serialize($_REQUEST);
       try {
            $db = PDO::getConnection();
            $stmt = $db->prepare('insert into '.$GLOBALS['table_prefix'].'restapi_request_log (url, cmd, ip, request, date) values(:url, :cmd, :ip, :request, now())');
            $stmt->bindParam('url', $_SERVER['REQUEST_URI'],PDO::PARAM_STR);
            $stmt->bindParam('cmd', $cmd, PDO::PARAM_STR);
            $stmt->bindParam('ip', $GLOBALS['remoteAddr'],PDO::PARAM_STR);
            $stmt->bindParam('request', $requestData, PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Exception $e) {
            $response->setError($e->getCode(), $e->getMessage());
        }
    }

    public static function enforceRequestLimit($limit)
    {
       $response = new Response();
       try {
            $db = PDO::getConnection();
            $stmt = $db->prepare('select count(cmd) as num from '.$GLOBALS['table_prefix'].'restapi_request_log where date > date_sub(now(),interval 1 minute)');
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_OBJ);
            if ($result->num > $limit) {
              $response->outputErrorMessage('Too many requests. Requests are limited to '.$limit.' per minute');
              die(0);
            }
        } catch (\Exception $e) {
            $response->setError($e->getCode(), $e->getMessage());
        }
    }

    public static function apiUrl($website)
    {
        $url = '';
        if (!empty($_SERVER['HTTPS'])) {
            if ($_SERVER['HTTPS'] !== 'off') {
                $url = 'https://';
            } //https
            else {
                $url = 'http://';
            } //http
        } else {
            $url = 'http://';
        } //http

        $api_url = str_replace('page=main&pi=restapi_test', 'page=call&pi=restapi', $_SERVER['REQUEST_URI']);
        $api_url = preg_replace('/\&tk\=[^&]*/', '', $api_url);
        $api_url = str_replace('page=main&pi=restapi', 'page=call&pi=restapi', $api_url);

        $url = $url.$website.$api_url;
        $url = rtrim($url, '/');

        return $url;
    }

    public static function parms($string,$data) {
        $indexed=$data==array_values($data);
        foreach($data as $k=>$v) {
            if(is_string($v)) $v="'$v'";
            if($indexed) $string=preg_replace('/\?/',$v,$string,1);
            else $string=str_replace(":$k",$v,$string);
        }
        return $string;
    }

    public static function encryptPassword($pass)
    {
        if (empty($pass)) {
            return '';
        }

        if (function_exists('hash')) {
            if (!in_array(ENCRYPTION_ALGO, hash_algos(), true)) {
                ## fallback, not that secure, but better than none at all
                $algo = 'md5';
            } else {
                $algo = ENCRYPTION_ALGO;
            }

            return hash($algo, $pass);
        } else {
            return md5($pass);
        }
    }

    public static function createUniqId() {
       return md5(uniqid(mt_rand()));
    }

    public static function method_allowed($class,$method) {
        if (empty($GLOBALS['restapi_whitelist'])) return true;
        if (in_array(strtolower($method),$GLOBALS['restapi_whitelist'][strtolower($class)])) return true;
        return false;
    }


    public static function trimArray($array) {
      $result = array();
      if (!is_array($array)) return $array;
      foreach ($array as $key => $val) {
        $testval = trim($val);
        if (isset($key) && !empty($testval)) {
          $result[$key] = $val;
        }
      }
      return $result;
    }

    public static function cleanCommaList($sList) {
        if (strpos($sList,',') === false) return $sList;
        $aList = explode(',',$sList);
        return join(',',Common::trimArray($aList));
    }


    public static function execQuery($sql, $params = array(), $single = false)
    {
       try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            foreach ($params as $param => $paramValue) {
                $stmt->bindParam($param, $paramValue[0],$paramValue[1]);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;
            if ($single && is_array($result) && isset($result[0])) {
                $result = $result[0];
            }
            return array(1, $result);
        } catch (\Exception $e) {
            return array(0, array("code"=>$e->getCode(),"message"=>$e->getMessage()));
        }
    }

    public static function userAttributeValue($user = 0,$attribute = 0) {
      $table_prefix = $GLOBALS['table_prefix'];
      $tables = $GLOBALS['tables'];
      $response = new Response();
      if (!$user || !$attribute) return;

      if (isset($tables["attribute"])) {
        $att_table = $tables["attribute"];
        $user_att_table = $tables["user_attribute"];
      } else {
        $att_table = "attribute";
        $user_att_table = "user_attribute";
      }
      $att = Common::execQuery("SELECT * FROM $att_table WHERE id= :attr_id", array('attr_id'=>array($attribute, PDO::PARAM_INT)));
      if(!$att[0]) return;
      $att = $response->object_to_array($att[1]);
      switch ($att[0]["type"]) {
        case "checkboxgroup":
     #     print "select value from $user_att_table where userid = $user and attributeid = $attribute";
          $params = array(
            'userid' => array($user, PDO::PARAM_INT),
            'attributeid'=> array($attribute, PDO::PARAM_INT)
          );
          $val_ids  = Common::execQuery("SELECT value FROM $user_att_table WHERE userid= :userid AND attributeid= :attributeid", $params);
          if(!$val_ids[0]) return;
          $val_ids = $response->object_to_array($val_ids[1]);
          if ($val_ids[0]['value']) {
     #       print '<br/>1 <b>'.$val_ids[0].'</b>';
            $val_ids[0]['value'] = Common::cleanCommaList($val_ids[0]['value']);
            ## make sure the val_ids as numbers
            $values = explode(',',$val_ids[0]['value']);
            $ids = array();
            foreach ($values as $valueIndex) {
              $iValue = sprintf('%d',$valueIndex);
              if ($iValue) {
                $ids[] = $iValue;
              }
            }
            if (!sizeof($ids)) return '';
            $val_ids[0]['value'] = join(',',$ids);
     #       print '<br/>2 <b>'.$val_ids[0].'</b>';
            $value = '';
            $res = Common::execQuery("select $table_prefix"."listattr_".$att["tablename"].".name as value
              from $user_att_table,$table_prefix"."listattr_".$att["tablename"]."
              where $user_att_table".".userid = ".$user." and
              $table_prefix"."listattr_".$att["tablename"].".id in ($val_ids[0]['value']) and
              $user_att_table".".attributeid = ".$attribute, array());
            if(!$res[0]) return '';
            $res = $response->object_to_array($res[1]);
            foreach ($res as $key => $row) {
               $value .= $row[0]['value'] . "; ";
            }
            $value = substr($value,0,-2);
          } else {
            $value = "";
          }
          break;
        case "select":
        case "radio":
          $res = Common::execQuery("select $table_prefix"."listattr_".$att["tablename"].".name as value
            from $user_att_table,$table_prefix"."listattr_".$att["tablename"]."
            where $user_att_table".".userid = ".$user." and
            $table_prefix"."listattr_".$att["tablename"].".id = $user_att_table".".value and
            $user_att_table".".attributeid = ".$attribute, array());
          if(!$res[0]) return '';
          $res = $response->object_to_array($res[1]);
          $value = $res[0]['value'];
          break;
        default:
          $res = Common::execQuery(sprintf('select value from %s where
            userid = %d and attributeid = %d',$user_att_table,$user,$attribute), array());
          if(!$res[0]) return '';
          $res = $response->object_to_array($res[1]);
          $value = $res[0]['value'];
      }
      return stripslashes($value);
    }

    public static function getSubscriberAttributeValues($email = '', $id = 0, $attributes = array()) {
      $table_prefix = $GLOBALS['table_prefix'];
      $tables = $GLOBALS['tables'];
      $response = new Response();

      if (!$email && !$id) return null;

      if (isset($tables["attribute"])) {
        $att_table = $tables["attribute"];
        $user_att_table = $tables["user_attribute"];
        $usertable = $tables["user"];
      } else {
        $att_table = "attribute";
        $user_att_table = "user_attribute";
        $usertable = "user";
      }

      $result = array();
      if ($email && !$id) {
        $params = array (
            'email' => array($email,PDO::PARAM_STR)
        );
        $userid = $this->execQuery("SELECT id FROM " . $usertable . " WHERE email= :email", $params);
        if(!$userid[0]) return;
        $userid = $response->object_to_array($userid[1]);
        $id = $userid[0]['id'];
      }
      if (!$id) return $result;
      //read all attributes or specified attributes
      $sql = 'SELECT id,name FROM ' . $att_table;
      if(!empty($attributes)){
        $sql .= " WHERE name IN('" . implode("','", $attributes) . "')";
      }
      $att_req = Common::execQuery($sql, array());
      if(!$att_req[0]) return;
      $att_req = $response->object_to_array($att_req[1]);
      foreach ($att_req as $key => $att) {
        $result[$att['name']] = Common::userAttributeValue($id, $att['id']);
      }
      return $result;
    }




}
