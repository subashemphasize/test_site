<?php
/**
 * Class represents records from table newsletter_user_subscription
 * {autogenerated}
 * @property int $subscription_id 
 * @property int $user_id 
 * @property int $list_id 
 * @property string $type enum('auto','user','unsubscribed')
 * @property int $is_active 
 * @see Am_Table
 */
class NewsletterUserSubscription extends Am_Record
{
    /** user subscribed automatically to this subscription */
    const TYPE_AUTO = 'auto';
    /** user specially agreed to receive this list */
    const TYPE_USER = 'user';
    /** user specially decied to unsubscribe from this list */
    const TYPE_UNSUBSCRIBED = 'unsubscribed';
    
    function disable()
    {
        $this->updateQuick('is_active', 0);
        return $this;
    }
    function enable($type = null)
    {
        $this->updateQuick('is_active', 1);
        if ($type !== null)
            $this->updateQuick('type', $type);
        return $this;
    }
    function unsubscribe()
    {
        $this->updateQuick(array(
            'is_active' => 0,
            'type' => self::TYPE_UNSUBSCRIBED,
        ));
        return $this;
    }
    function isUnsubscribed()
    {
        return $this->type == self::TYPE_UNSUBSCRIBED;
    }
}

class NewsletterUserSubscriptionTable extends Am_Table
{
    protected $_key = 'subscription_id';
    protected $_table = '?_newsletter_user_subscription';
    
    /**
     * Add or enable subscription
     * @param type $user_id
     * @param type $list_id
     * @param type $type
     * @return type 
     */
    function add($user_id, $list_id, $type)
    {

        $sub = $this->findFirstBy(array(
            'user_id' => $user_id,
            'list_id' => $list_id
            ));

        if ($sub) {
            $sub->type = $type;
            $sub->is_active = 1;
        } else {
            $sub = $this->createRecord();
            $sub->user_id = $user_id;
            $sub->list_id = $list_id;
            $sub->type = $type;
            $sub->is_active = 1;
        }

        $sub->save();
        return $sub;
    }
    function checkSubscriptions(User $user)
    {
        if (!empty($user->unsubscribed))
        {
            $this->_db->query("UPDATE $this->_table SET is_active = 0 WHERE user_id=?d", $user->pk());
            return;
        }
        
        // array of NewsletterList user has access at all
        $ids = array();
        
        foreach ($this->getDi()->newsletterListTable->getAllowed($user) as $list)
        {
            $ids[ $list->pk() ] = array( 'l' => $list );
        }
        foreach ($this->findByUserId($user->pk()) as $r)
        {
            $ids[ $r->list_id]['s'] = $r;
        }
        ////
        foreach ($ids as $list_id => $record)
        {
            /* @var $availableList NewsletterList */
            $availableList = @$record['l'];
            /* @var $existingSub NewsletterUserSubscription */
            $existingSub = @$record['s'];
            
            if ($existingSub)
            {
                if (!$availableList) 
                {
                    // if not available now, de-activate
                    $existingSub->disable();
                    continue;
                } elseif ($existingSub->isUnsubscribed()) {
                    // if user unsubscribed previously, make sure it disabled
                    $existingSub->disable();
                    continue;
                } else  { // status = user or status = auto
                    // if user was subscribed before, enable it
                    $r->enable();
                }
            } elseif ($user->data()->get(Bootstrap_Newsletter::NEWSLETTER_SIGNUP_DATA) == 1) {
                $this->add($user->pk(), $availableList->pk(), NewsletterUserSubscription::TYPE_USER);
            } elseif (($data = $user->data()->get(Bootstrap_Newsletter::NEWSLETTER_SIGNUP_DATA)) && 
                !empty($data[$availableList->pk()])) {
                $this->add($user->pk(), $availableList->pk(), NewsletterUserSubscription::TYPE_USER);
            } elseif ($availableList && $availableList->auto_subscribe) { // no existing list - create if 'auto'
                $this->add($user->pk(), $availableList->pk(), NewsletterUserSubscription::TYPE_AUTO);
            }
        }
    }
    /**
     * Return ids of lists user is enabled to receive, even if currently subscription is not available 
     * (say subscription expired)
     * @param type $user_id
     * @return type 
     */
    function getSubscribedIds($user_id)
    {
        return $this->_db->selectCol("SELECT list_id FROM $this->_table WHERE user_id=?d AND type <> ?", $user_id, 
            NewsletterUserSubscription::TYPE_UNSUBSCRIBED);
    }
    
    /**
     * Update list of subscriptions by admin - it does not check any permissions 
     * immediately, but subscriptions may be deleted during @link checkSubscriptions()
     */
    function adminSetIds($user_id, array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        // remove ids not present in the list
        $this->_db->query("
            DELETE FROM $this->_table 
            WHERE user_id=?d { AND list_id NOT IN (?a) } ", $user_id, $ids ? $ids : DBSIMPLE_SKIP);
        // insert ids not present in the database - duplicates will be automatically removed
        if ($ids)
        {
            // enable unsubscribed
            $this->_db->query("
                UPDATE $this->_table 
                SET is_active = 1, `type` = ?
                WHERE user_id=?d AND list_id IN (?a) AND `type` = ? ", 
                NewsletterUserSubscription::TYPE_AUTO,
                $user_id, $ids, NewsletterUserSubscription::TYPE_UNSUBSCRIBED );
            // insert not-inserted
            $sql = "INSERT IGNORE INTO $this->_table
            (user_id, list_id, type, is_active) 
            VALUES \n";
            foreach ($ids as $id)
            {
                $sql .= sprintf("(%d,%d,'%s',%d),\n", 
                    $user_id, $id, NewsletterUserSubscription::TYPE_AUTO, 1);
            }
            $sql = trim($sql, ",\n");
            $this->_db->query($sql);
        }
    }
}