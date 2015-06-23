<?php

/**
 *	phpIPAM API class to work with vrfs
 *
 *
 */

class Vrfs_controller extends Common_functions {

	/* public variables */
	public $_params;

	/* protected variables */
	protected $valid_keys;

	/* object holders */
	protected $Database;			// Database object
	protected $Sections;			// Sections object
	protected $Tools;				// Tools object
	protected $Admin;				// Admin object


	/**
	 * __construct function
	 *
	 * @access public
	 * @param class $Database
	 * @param class $Tools
	 * @param mixed $params		// post/get values
	 * @return void
	 */
	public function __construct($Database, $Tools, $params, $Response) {
		$this->Database = $Database;
		$this->Tools 	= $Tools;
		$this->_params 	= $params;
		$this->Response = $Response;
		// init required objects
		$this->init_object ("Admin", $Database);
		$this->init_object ("Subnets", $Database);
		// set valid keys
		$this->set_valid_keys ("vrf");
	}






	/**
	 * Returns json encoded options
	 *
	 * @access public
	 * @return void
	 */
	public function OPTIONS () {
		// validate
		$this->validate_options_request ();

		// methods
		$result['methods'] = array(
								array("href"=>"/api/vrfs/".$this->_params->app_id."/", 		"methods"=>array(array("rel"=>"options", "method"=>"OPTIONS"))),
								array("href"=>"/api/vrfs/".$this->_params->app_id."/{id}/", "methods"=>array(array("rel"=>"read", 	"method"=>"GET"),
																											 array("rel"=>"create", "method"=>"POST"),
																											 array("rel"=>"update", "method"=>"PATCH"),
																											 array("rel"=>"delete", "method"=>"DELETE"))),
							);
		# result
		return array("code"=>200, "data"=>$result);
	}






	/**
	 * Read vrf
	 *
	 *	identifiers:
	 *		- NONE				// returns all VRFs
	 *		- {id}				// returns VRF by id
	 *		- {id}/subnets/		// subnets inside vrf
	 *
	 *
	 * @access public
	 * @return void
	 */
	public function GET () {
		// all
		if (!isset($this->_params->id)) {
			$result = $this->Tools->fetch_all_objects ("vrf", 'vrfId');
			// check result
			if($result===false)						{ $this->Response->throw_exception(404, 'No vrfs configured'); }
			else									{ return array("code"=>200, "data"=>$this->prepare_result ($result, null, true, true)); }
		}
		// subnets
		elseif (isset($this->_params->id2)) {
			// subnets
			if ($this->_params->id2 == "subnets") {
				// validate
				$this->validate_vrf ();
				// fetch
				$result = $this->Tools->fetch_multiple_objects ("subnets", "vrfId", $this->_params->id, 'subnet', true);
				// check result
				if($result===false)					{ $this->Response->throw_exception(404, 'No subnets belonging to this vrf'); }
				else								{ return array("code"=>200, "data"=>$this->prepare_result ($result, "subnets", true, true)); }
			}
			// error
			else {
													{ $this->Response->throw_exception(400, "Invalid identifier"); }
			}
		}
		// by id
		else {
			// validate
			$this->validate_vrf ();
			// fetch
			$result = $this->Tools->fetch_object ("vrf", "vrfId", $this->_params->id);
			// check result
			if($result==NULL)						{ $this->Response->throw_exception(404, "VRF not found"); }
			else									{ return array("code"=>200, "data"=>$this->prepare_result ($result, null, true, true)); }
		}
	}





	/**
	 * HEAD, no response
	 *
	 * @access public
	 * @return void
	 */
	public function HEAD () {
		return $this->GET ();
	}





	/**
	 * Creates new VRF
	 *
	 * @access public
	 * @return void
	 */
	public function POST () {
		# check for valid keys
		$values = $this->validate_keys ();

		# validate input
		$this->validate_vrf_edit ();

		# execute update
		if(!$this->Admin->object_modify ("vrf", "add", "vrfId", $values))
													{ $this->Response->throw_exception(500, "VRF creation failed"); }
		else {
			//set result
			return array("code"=>201, "data"=>"VRF created", "location"=>"/api/".$this->_params->app_id."/vrfs/".$this->Admin->lastId."/");
		}
	}





	/**
	 * Updates existing vrf
	 *
	 * @access public
	 * @return void
	 */
	public function PATCH () {
		# verify
		$this->validate_vrf ();
		# check that it exists
		$this->validate_vrf_edit ();

		# rewrite id
		$this->_params->vrfId = $this->_params->id;
		unset($this->_params->id);

		# validate and prepare keys
		$values = $this->validate_keys ();

		# execute update
		if(!$this->Admin->object_modify ("vrf", "edit", "vrfId", $values))
													{ $this->Response->throw_exception(500, "Vrf edit failed"); }
		else {
			//set result
			return array("code"=>200, "data"=>"VRF updated");
		}
	}






	/**
	 * Deletes existing vrf
	 *
	 * @access public
	 * @return void
	 */
	public function DELETE () {
		# check that vrf exists
		$this->validate_vrf ();

		# set variables for update
		$values["vrfId"] = $this->_params->id;

		# execute delete
		if(!$this->Admin->object_modify ("vrf", "delete", "vrfId", $values))
													{ $this->Response->throw_exception(500, "Vrf delete failed"); }
		else {
			// delete all references
			$this->Admin->remove_object_references ("subnets", "vrfId", $this->_params->id);

			// set result
			return array("code"=>200, "data"=>"VRF deleted");
		}
	}










	/* @validations ---------- */



	/**
	 * Validates VRF - checks if it exists
	 *
	 * @access private
	 * @return void
	 */
	private function validate_vrf () {
		// validate id
		if(!isset($this->_params->id))														{ $this->Response->throw_exception(400, "Vrf Id is required");  }
		// validate number
		if(!is_numeric($this->_params->id))													{ $this->Response->throw_exception(400, "Vrf Id must be numeric"); }
		// check that it exists
		if($this->Tools->fetch_object ("vrf", "vrfId", $this->_params->id) === false )		{ $this->Response->throw_exception(400, "Invalid VRF id"); }
	}


	/**
	 * Validates VRF on add and edit
	 *
	 * @access private
	 * @return void
	 */
	private function validate_vrf_edit () {
		// check for POST method
		if($_SERVER['REQUEST_METHOD']=="POST") {
			// check name
			if(strlen($this->_params->name)==0)												{ $this->Response->throw_exception(400, "VRF name is required"); }
			// check that it exists
			if($this->Tools->fetch_object ("vrf", "name", $this->_params->name) !== false )	{ $this->Response->throw_exception(400, "VRF with that name already exists"); }
		}
		// update check
		else {
			// old values
			$vrf_old = $this->Tools->fetch_object ("vrf", "vrfId", $this->_params->id);

			if(isset($this->_params->name)) {
				if ($this->_params->name != $vrf_old->name) {
					if($this->Tools->fetch_object ("vrf", "name", $this->_params->name))	{ $this->Response->throw_exception(400, "VRF with that name already exists"); }
				}
			}
		}
	}

}

?>