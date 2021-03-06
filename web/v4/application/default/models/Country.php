<?php
/**
 * Class represents records from table countries
 * {autogenerated}
 * @property int $country_id 
 * @property string $country 
 * @property string $title 
 * @property int $tag 
 * @property string $status enum('added','changed')
 * @see Am_Table
 */

class Country extends Am_Record 
{
}

class CountryTable extends Am_Table {
    protected $_key = 'country_id';
    protected $_table = '?_country';
    protected $_recordClass = 'Country';
    
    function getTitleByCode($code){
        return $this->_db->selectCell("SELECT title
            FROM ?_country
            WHERE country=?", $code);
    }
    function getOptions($addEmpty = false){
        //if admin show all countries, if user show only active countries
        $where = defined('AM_ADMIN') ? '' : 'WHERE tag>=0';
        $res = $this->_db->selectCol("SELECT country as ARRAY_KEY,
                CASE WHEN tag<0 THEN CONCAT(title, ' (disabled)') ELSE title END
                FROM ?_country $where
                ORDER BY tag DESC, title");
        
        $translatedNames = array();
        
        if (strpos($locale = $this->getDi()->app->getDefaultLocale(), 'en')!==0)
        {
            $locale = new Am_Locale;
            $tr = $locale->getTerritoryNames();
            foreach ($res as $k => $v)
                if (array_key_exists($k, $tr)) $res[$k] = $tr[$k];
        }
        if ($res && $addEmpty)
            $res = array_merge(array('' => ___('[Select country]')),$res);
        return $res;
    }
}
