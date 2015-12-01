<?php

namespace phpListRestapi;

defined('PHPLISTINIT') || die;

class Subscribers
{
    /**
     * Get all the Subscribers in the system.
     *
     * <p><strong>Parameters:</strong><br/>
     * [order_by] {string} name of column to sort, default "id".<br/>
     * [order] {string} sort order asc or desc, default: asc.<br/>
     * [limit] {integer} limit the result, default 100 (max 100)<br/>
     * [offset] {integer} offset of the result, default 0.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * List of Subscribers.
     * </p>
     */
    public static function subscribersGet($order_by = 'id', $order = 'asc', $limit = 100, $offset = 0)
    {

        if (isset($_REQUEST['order_by']) && !empty($_REQUEST['order_by'])) {
            $order_by = $_REQUEST['order_by'];
        }
        if (isset($_REQUEST['order']) && !empty($_REQUEST['order'])) {
            $order = $_REQUEST['order'];
        }
        if (isset($_REQUEST['limit']) && !empty($_REQUEST['limit'])) {
            $limit = $_REQUEST['limit'];
        }
        if (isset($_REQUEST['offset']) && !empty($_REQUEST['offset'])) {
            $offset = $_REQUEST['offset'];
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $params = array (
            'order_by' => array($order_by,PDO::PARAM_STR),
            'order' => array($order,PDO::PARAM_STR),
            'limit' => array($limit,PDO::PARAM_INT),
            'offset' => array($offset,PDO::PARAM_INT),
        );

        Common::select('Subscribers', 'SELECT * FROM '.$GLOBALS['tables']['user']." ORDER BY :order_by :order LIMIT :limit OFFSET :offset;",$params);
    }

    /**
     * Get the total of Subscribers in the system.
     *
     * <p><strong>Parameters:</strong><br/>
     * none
     * </p>
     * <p><strong>Returns:</strong><br/>
     * Number of subscribers.
     * </p>
     */
    public static function subscribersCount()
    {
        Common::select('Subscribers', 'SELECT count(id) as total FROM '.$GLOBALS['tables']['user'],array(),true);
    }

    /**
     * Get one Subscriber by ID.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*id] {integer} the ID of the Subscriber.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * One Subscriber only.
     * </p>
     */
    public static function subscriberGet($id = 0)
    {
        if ($id == 0) {
            $id = sprintf('%d',$_REQUEST['id']);
        }
        if (!is_numeric($id) || empty($id)) {
            Response::outputErrorMessage('invalid call');
        }

        $params = array(
            'id' => array($id,PDO::PARAM_INT),
        );
        Common::select('Subscriber', 'SELECT * FROM '.$GLOBALS['tables']['user']." WHERE id = :id;",$params, true);
    }

    /**
     * Get one Subscriber by email address.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*email] {string} the email address of the Subscriber.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * One Subscriber only.
     * </p>
     */
    public static function subscriberGetByEmail($email = '')
    {
        if (empty($email)) {
            $email = $_REQUEST['email'];
        }
        $params = array(
            'email' => array($email,PDO::PARAM_STR)
        );
        Common::select('Subscriber', 'SELECT * FROM '.$GLOBALS['tables']['user']." WHERE email = :email;",$params, true);
    }

    /**
     * Get one Subscriber by foreign key.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*foreignkey] {string} the foreign key of the Subscriber.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * One Subscriber only.
     * </p>
     */
    public static function subscriberGetByForeignkey($foreignkey = '')
    {
        if (empty($foreignkey)) {
            $foreignkey = $_REQUEST['foreignkey'];
        }
        $params = array(
            'foreignkey' => array($foreignkey,PDO::PARAM_STR)
        );
        Common::select('Subscriber', 'SELECT * FROM '.$GLOBALS['tables']['user']." WHERE foreignkey = :foreignkey;",$params, true);
    }

    /**
     * Add one Subscriber.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*email] {string} the email address of the Subscriber.<br/>
     * [*confirmed] {integer} 1=confirmed, 0=unconfirmed.<br/>
     * [*htmlemail] {integer} 1=html emails, 0=no html emails.<br/>
     * [*foreignkey] {string} Foreign key.<br/>
     * [*subscribepage] {integer} subscribe page to sign up to.<br/>
     * [*password] {string} The password for this Subscriber.<br/>
     * [*disabled] {integer} 1=disabled, 0=enabled<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * The added Subscriber.
     * </p>
     */
    public static function subscriberAdd()
    {
        $sql = 'INSERT INTO '.$GLOBALS['tables']['user'].'
          (email, confirmed, foreignkey, htmlemail, password, passwordchanged, subscribepage, disabled, entered, uniqid)
          VALUES (:email, :confirmed, :foreignkey, :htmlemail, :password, now(), :subscribepage, :disabled, now(), :uniqid);';

        $encPwd = Common::encryptPassword($_REQUEST['password']);
        $uniqueID = Common::createUniqId();
        if (!validateEmail($_REQUEST['email'])) {
            Response::outputErrorMessage('invalid email address');
        }

        try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam('email', $_REQUEST['email'], PDO::PARAM_STR);
            $stmt->bindParam('confirmed', $_REQUEST['confirmed'], PDO::PARAM_BOOL);
            $stmt->bindParam('htmlemail', $_REQUEST['htmlemail'], PDO::PARAM_BOOL);
            /* @@todo ensure uniqueness of FK */
            $stmt->bindParam('foreignkey', $_REQUEST['foreignkey'], PDO::PARAM_STR);
            $stmt->bindParam('password', $encPwd, PDO::PARAM_STR);
            $stmt->bindParam('subscribepage', $_REQUEST['subscribepage'], PDO::PARAM_INT);
            $stmt->bindParam('disabled', $_REQUEST['disabled'], PDO::PARAM_BOOL);
            $stmt->bindParam('uniqid', $uniqueID, PDO::PARAM_STR);
            $stmt->execute();
            $id = $db->lastInsertId();
            $db = null;
            self::SubscriberGet($id);
        } catch (\Exception $e) {
            Response::outputError($e);
        }
    }

    /**
     * Add a Subscriber with lists.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*email] {string} the email address of the Subscriber.<br/>
     * [*foreignkey] {string} Foreign key.<br/>
     * [*htmlemail] {integer} 1=html emails, 0=no html emails.<br/>
     * [*subscribepage] {integer} subscribepage to sign up to.<br/>
     * [*lists] {string} comma-separated list IDs.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * The added Subscriber.
     * </p>
     */
    public static function subscribe()
    {
        $sql = 'INSERT INTO '.$GLOBALS['tables']['user'].'
          (email, htmlemail, foreignkey, subscribepage, entered, uniqid)
          VALUES (:email, :htmlemail, :foreignkey, :subscribepage, now(), :uniqid);';

        $uniqueID = Common::createUniqId();
        $subscribePage = sprintf('%d',$_REQUEST['subscribepage']);
        if (!validateEmail($_REQUEST['email'])) {
            Response::outputErrorMessage('invalid email address');
        }

        $listNames = '';
        $lists = explode(',',$_REQUEST['lists']);

        try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam('email', $_REQUEST['email'], PDO::PARAM_STR);
            $stmt->bindParam('htmlemail', $_REQUEST['htmlemail'], PDO::PARAM_BOOL);
            /* @@todo ensure uniqueness of FK */
            $stmt->bindParam('foreignkey', $_REQUEST['foreignkey'], PDO::PARAM_STR);
            $stmt->bindParam('subscribepage', $subscribePage, PDO::PARAM_INT);
            $stmt->bindParam('uniqid', $uniqueID, PDO::PARAM_STR);
            $stmt->execute();
            $subscriberId = $db->lastInsertId();
            foreach ($lists as $listId) {
                $stmt = $db->prepare('replace into '.$GLOBALS['tables']['listuser'].' (userid,listid,entered) values(:userid,:listid,now())');
                $stmt->bindParam('userid', $subscriberId, PDO::PARAM_INT);
                $stmt->bindParam('listid', $listId, PDO::PARAM_INT);
                $stmt->execute();
                $listNames .= "\n  * ".listname($listId);
            }
            $subscribeMessage = getUserConfig("subscribemessage:$subscribePage", $subscriberId);
            $subscribeMessage = str_replace('[LISTS]',$listNames,$subscribeMessage);

            $subscribePage = sprintf('%d',$_REQUEST['subscribepage']);
            sendMail($_REQUEST['email'], getConfig("subscribesubject:$subscribePage"), $subscribeMessage );
            addUserHistory($_REQUEST['email'], 'Subscription', 'Subscription via the Rest-API plugin');
            $db = null;
            self::SubscriberGet($subscriberId);
        } catch (\Exception $e) {
            Response::outputError($e);
        }
    }
    /**
     * Update one Subscriber.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*id] {integer} the ID of the Subscriber.<br/>
     * [*email] {string} the email address of the Subscriber.<br/>
     * [*confirmed] {integer} 1=confirmed, 0=unconfirmed.<br/>
     * [*htmlemail] {integer} 1=html emails, 0=no html emails.<br/>
     * [*rssfrequency] {integer}<br/>
     * [*password] {string} The password to this Subscriber.<br/>
     * [*disabled] {integer} 1=disabled, 0=enabled<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * The updated Subscriber.
     * </p>
     */
    public static function subscriberUpdate()
    {
        $sql = 'UPDATE '.$GLOBALS['tables']['user'].' SET email=:email, confirmed=:confirmed, htmlemail=:htmlemail WHERE id=:id;';

        $id = sprintf('%d',$_REQUEST['id']);
        if (empty($id)) {
            Response::outputErrorMessage('invalid call');
        }
        try {
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam('id', $id, PDO::PARAM_INT);
            $stmt->bindParam('email', $_REQUEST['email'], PDO::PARAM_STR);
            $stmt->bindParam('confirmed', $_REQUEST['confirmed'], PDO::PARAM_BOOL);
            $stmt->bindParam('htmlemail', $_REQUEST['htmlemail'], PDO::PARAM_BOOL);
            $stmt->execute();
            $db = null;
            self::SubscriberGet($id);
        } catch (\Exception $e) {
            Response::outputError($e);
        }
    }

    /**
     * Delete a Subscriber.
     *
     * <p><strong>Parameters:</strong><br/>
     * [*id] {integer} the ID of the Subscriber.<br/>
     * </p>
     * <p><strong>Returns:</strong><br/>
     * The deleted Subscriber ID.
     * </p>
     */
    public static function subscriberDelete()
    {
        $sql = 'DELETE FROM '.$GLOBALS['tables']['user'].' WHERE id=:id;';
        try {
            if (!is_numeric($_REQUEST['id'])) {
                Response::outputErrorMessage('invalid call');
            }
            $db = PDO::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->bindParam('id', $_REQUEST['id'], PDO::PARAM_INT);
            $stmt->execute();
            $db = null;
            Response::outputDeleted('Subscriber', sprintf('%d',$_REQUEST['id']));
        } catch (\Exception $e) {
            Response::outputError($e);
        }
    }

   /**
     * Get all the Subscribers in the system with extra attributes.
     *
     * <p><strong>Parameters:</strong><br/>
     * [order_by] {string} name of column to sort, default "id".<br/>
     * [order] {string} sort order asc or desc, default: asc.<br/>
     * [limit] {integer} limit the result, default 100 (max 100)<br/>
     * [offset] {integer} offset of the result, default 0.<br/>
     * [attributes] {string} comma delimits attributes name if empty then return all available attributes else fetch only specified attributes
     * </p>
     * <p><strong>Returns:</strong><br/>
     * List of Subscribers.
     * </p>
     */
    public static function subscribersGetWithAttributes($order_by = 'id', $order = 'asc', $limit = 100, $offset = 0, $attributes = '')
    {

        if (isset($_REQUEST['order_by']) && !empty($_REQUEST['order_by'])) {
            $order_by = $_REQUEST['order_by'];
        }
        if (isset($_REQUEST['order']) && !empty($_REQUEST['order'])) {
            $order = $_REQUEST['order'];
        }
        if (isset($_REQUEST['limit']) && !empty($_REQUEST['limit'])) {
            $limit = $_REQUEST['limit'];
        }
        if (isset($_REQUEST['offset']) && !empty($_REQUEST['offset'])) {
            $offset = $_REQUEST['offset'];
        }

        if (isset($_REQUEST['attributes']) && !empty($_REQUEST['attributes'])) {
            $attributes = $_REQUEST['attributes'];
        }


        // if ($limit > 100) {
        //     $limit = 100;
        // }

        if(!empty($attributes)){
            $attributes = explode(",", $attributes);
        }

        // NOT TAKING INT VALUES THUS COMMENTED HERE
        // $params = array (
        //     'order_by' => array($order_by,PDO::PARAM_STR),
        //     'order' => array($order,PDO::PARAM_STR),
        //     'limit' => array($limit,PDO::PARAM_INT),
        //     'offset' => array($offset,PDO::PARAM_INT)
        // );

        $sql = "SELECT * FROM " . $GLOBALS['tables']['user'] . " ORDER BY $order_by $order LIMIT $limit OFFSET $offset;";
        $result = Common::execQuery($sql, array());
        $response = new Response();
        if($result[0]){
            $result = $response->object_to_array($result[1]);
            foreach ($result as $key => $row) {
               $result_attribues = Common::getSubscriberAttributeValues('', $row['id'], $attributes);
               $result[$key] = array_merge($row, $result_attribues);
            }
            $response->setData('Subscribers', $result);
        }else{
            $response->setError($result[1]['code'], $result[1]['message']);
        }
        $response->output();
    }


}
