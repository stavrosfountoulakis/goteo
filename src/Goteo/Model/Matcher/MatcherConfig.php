<?php
/*
 * This file is part of the Goteo Package.
 *
 * (c) Platoniq y Fundación Goteo <fundacion@goteo.org>
 *
 * For the full copyright and license information, please view the README.md
 * and LICENSE files that was distributed with this source code.
 */

namespace Goteo\Model\Matcher;

class MatcherConfig extends  \Goteo\Core\Model {
    
  public
   $matcher,
   $budget,
   $algorithm,
   $max_donation_per_invest,
   $max_donation_per_project,
   $percent_of_donation,
   $donation_per_project;

   protected $Table = 'matcher_conf';
   protected static $Table_static = 'matcher_conf';

  public static function get($matcher) {

    $query = static::query("SELECT * FROM matcher_conf WHERE matcher = ?", array($matcher));
    $matcher_conf = $query->fetchObject(__CLASS__);
    return $matcher_conf;
  }

  public function save(&$errors = array()) {
    if (!$this->validate($errors)) return false;

    try {
        $sql = "REPLACE INTO matcher_conf (matcher,budget,algorithm,max_donation_per_invest,max_donation_per_project,percent_of_donation,donation_per_project) 
                VALUES(:matcher,:budget,:algorithm,:max_donation_per_invest,:max_donation_per_project,:percent_of_donation,:donation_per_project)";
        $values = array(':matcher' => $this->matcher,
                        ':budget' => $this->budget,
                        ':algorithm' => $this->algorithm,
                        ':max_donation_per_invest' => $this->max_donation_per_invest,
                        ':max_donation_per_project' => $this->max_donation_per_project,
                        ':percent_of_donation' => $this->percent_of_donation,
                        ':donation_per_project' => $this->donation_per_project);

        self::query($sql, $values);
    } catch(\PDOException $e) {
        $errors[] = "Error updating configuration. " . $e->getMessage();
        return false;
    }

    return true;

  }

  public function validate(&$errors = array()) {

    if ($this->algorithm == "match") {
      $this->max_donation_per_invest = 0;
      $this->max_donation_per_project = 0;
      if (!isset($this->percent_of_donation)) return false;
      if (!isset($this->donation_per_project)) return false;
      return true;
    } else if ($this->algorithm == "multiply") {
      $this->percent_of_donation = 0;
      $this->donation_per_project = 0;
      if (!isset($this->max_donation_per_invest)) return false;
      if (!isset($this->max_donation_per_project)) return false;
      return true;
    }
    return false;
  }

}

