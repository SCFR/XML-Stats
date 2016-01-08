<?php
/**
 * Parse the Loadout and base Stats/Equipements of a given ship
 * Is reponsible for calling all required items.
 * @package SC-XML-Stats
 * @subpackage classes
 */

	Class SC_Loadout implements SC_Parser {

		private $file;
		private $XML;
		private $XMLShip;
		private $error;
		private $sucess= true;
		private $hardpoints;

		private $loadout = array();
		private $RELATIVE_PATH = "../";

		function __construct($file) {
			try {
				$this->file = $file;
				$this->setFileName();
				$this->parseLoadout(simplexml_load_file($file));
			}
			catch(Exception $e) {
				$this->error = $e->getMessage();
			}

		}

		/**
     * Does the parsing of the ShipImplementation
     * @throws Exception If it can't get the ShipImplementation
     */
		function private parseShip() {
			$path = $this->getShipPath();
				if($path) {
					$this->XMLShip = simplexml_load_file($path);
					$this->getImplementation();
				}
				else throw new Exception("CantFindShipImplementation");
		}

		/**
     * Recurs on every HardPoints of the base ship
		 * to get its children, and puts every one of them in a 1D array.
     * @param SimpleXMLElement $xml XML of Hardpoint to parse
     * @param array &$raw the array in which to output
     */
		private function recurHp($xml,&$raw) {
			if($xml->Parts) {
				foreach($xml->Parts->Part as $part) {
					$raw[(string) $part["name"]] = $part;
					$this->recurHp($part,$raw);
				}
			}
		}

		/**
     * Get the types and subtypes of a given HardPoint on a ship implementation
		 * Output as an array containing types and its subtypes, indexed by types by default
		 * Or by numerical indexes if strictArray is set to true
     * @param SimpleXMLElement $hp XML of Hardpoint
     * @param boolean $strictArray type or int indexes
     */
		private function getHpTypes($hp,$strictArray=false) {
			$types = false;
			if($hp->ItemPort->Types) {
				foreach($hp->ItemPort->Types->Type as $type) {
					$types[(string) $type["type"]] = array("type" => (string) $type["type"],"subtypes" => explode(',', (string) $type["subtypes"]));
				}
				if($strictArray) $types = array_values($types);
			}
			return $types;
		}

		/**
     * Finds out if the hardpoint is of a given type or not
		 * @param array|string $types the type(s) to test for
     * @param SimpleXMLElement $hp XML of Hardpoint
	   * @return boolean
     */
		private function hpisOfType($types, $hp) {
			$hpTypes = $this->getHpTypes($hp);
			if(!$hpTypes) return false;
			foreach((array) $types as $type) {
				if(isset($hpTypes[$type])) return true;
			}
			return false;
		}

		/**
     * Return the base informations in ShipImplementation of a given hardpoint
     * @param SimpleXMLElement $h XML of Hardpoint
	   * @return array
     */
		private function hpReturnInfo($h) {
			return array(
				"name"					=> (string) $h["name"],
				"minSize"				=> (int) $h->ItemPort["minsize"],
				"maxSize"				=> (int) $h->ItemPort["maxsize"],
				"displayName"		=> (string) $h->ItemPort["display_name"],
				"flags"					=> (string) $h->ItemPort["flags"],
				"requiredTags"	=> (string) $h->ItemPort["requiredTags"],
				"types"					=> $this->getHpTypes($h,true),
			);
		}

		/**
     * Main function to parse ShipImplementation
		 * Starts of by calling { @link recurHp()} to build the array of items,
		 * get main stats of the ship, then get worthwhile hardpoints into { @link loadout}
     * @param SimpleXMLElement $h XML of Hardpoint
	   * @return array
     */
		function getImplementation() {
				// Setting up
			$raw = array();
			$this->recurHp($this->XMLShip, $raw);
			foreach($this->XMLShip->attributes() as $name=>$value) {
				$mainVehicle[$name] = (string) $value;
			}

			$this->loadout += $mainVehicle;

				// Get mains stats
			$mainPart = reset($raw);

				// Get HardPoints that are worthwhile;
			foreach($raw as $hpname => $hp) {
				if($this->hpisOfType(array("Turret", "WeaponGun", "WeaponMissile"),$hp)) $this->loadout["HARDPOINTS"]["WEAPONS"][] = $this->hpReturnInfo($hp);
				elseif($this->hpisOfType(array("MainThruster"),$hp)) 			$this->loadout["HARDPOINTS"]["ENGINES"][] 	= $this->hpReturnInfo($hp);
				elseif($this->hpisOfType(array("ManneuverThruster"),$hp)) $this->loadout["HARDPOINTS"]["THRUSTERS"][] = $this->hpReturnInfo($hp);
			}

		}

		/**
     * Finds and sets the path of the ShipImplementation
	   * @return string|boolean the path or false
     */
		function getShipPath() {
			global $_SETTINGS;
			$base = $_SETTINGS['STARCITIZEN']['scripts'].$_SETTINGS['STARCITIZEN']['PATHS']['ship'];
			$file = false;

				// The ship is a base one, or has, for some reason, a base implementation as a variant.
			if(file_exists($base.$this->itemName.".xml")) $file = $base.$this->itemName.".xml";
			else {
				// The ship is a variant
					// can take TWO forms:  CONST_BASESHIP_VARIANT : easy one.
					// OR : CONST_VARIANTNAMECLOSETOBASE. (eg : 300i vs 315p)
					$t = preg_match("~^(.*)_([^_]*)$~U", $this->itemName, $match);
					if($t) {
							// Easy form
						if(file_exists($base.$match[1].".xml")) $file = $base.$match[1].".xml";
						else {
								// Or hard one
							for($i = strlen($match[2]); $i > 0; $i--) {
								$try = str_split($match[2], $i);
								$files = glob($base.$match[1]."_".$try[0]."*");
								if($files && sizeof($files) == 1 && file_exists($files[0])) $file = $files[0];
							}
						}
					}
				}

			return $file;
		}

		/**
     * Main function to parse ShipImplementation
		 * Starts of by calling { @link recurHp()} to build the array of items,
		 * get main stats of the ship, then get worthwhile hardpoints into { @link loadout}
     * @param SimpleXMLElement $h XML of Hardpoint
	   * @return array
     */
		function parseLoadout($xml) {
			$this->parseShip();
			$this->parseEquipment();
		}

		function setFileName() {
			$match = preg_match("~Default_Loadout_(.*).xml$~U", $this->file,$try);
			if($match) $this->itemName = $try[1];
			else throw new Exception("CantExtractLoadoutName");
		}

		function parseEquipment() {

			foreach($this->XML->Items->Item as $item) {
				$equipements[(string) $item["portName"]] = $item;
			}

			foreach($this->loadout["HARDPOINTS"] as $hpType => $hpList) {
				foreach($hpList as $i => $hp) {
					$put = false;
					if(isset($equipements[$hp["name"]])) {
						try	{
							switch($hpType) {
								case "ENGINES":
									$s = new SC_Engine((array) $equipements[$hp["name"]]);
								break;
								case "WEAPONS":
									$s = new SC_Weapon((array) $equipements[$hp["name"]]);
								break;
							}
							if(isset($s))	$put =	$s->returnHardpoint((string) $equipements[$hp["name"]]['portName']);
						}
						catch(Exception $e) {
								echo $e->getMessage();
								$this->error[] = $hpType." : ".$e->getMessage();
						}

						if($put) $this->loadout["HARDPOINTS"][$hpType][$i] += $put;
					}
				}
			}
		}



		function saveJson($folder) {
			global $_SETTINGS;
      $path = $_SETTINGS["SOFT"]["jsonPath"].$folder;
      if(!is_dir($path)) mkdir($path, 0777, true);

			file_put_contents($path.$this->itemName.".json", json_encode($this->getData()));
		}


		function getError() {
			return $this->error;
		}

		function getSucess() {
			return $this->sucess;
		}


		function getData() {
			return $this->loadout;
		}


	}
?>
