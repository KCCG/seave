<?php

#############################################
# FETCH ALL ANNOTATIONS AND THEIR LATEST VERSIONS
#############################################

function fetch_all_annotations($all_annotations = NULL) { // Optional parameter to return all annotations
	$annotations = array();
	
	$sql = "SELECT ";
		$sql .= "ANNOTATIONS.annotations.name, ";
		$sql .= "ANNOTATIONS.annotations.description, ";
		$sql .= "ANNOTATIONS.annotations.active, ";
		$sql .= "ANNOTATIONS.groups.group_name, ";
		$sql .= "ANNOTATIONS.annotation_updates.version, ";
		$sql .= "DATE(ANNOTATIONS.annotation_updates.update_time) as update_time, ";
		$sql .= "ANNOTATIONS.update_methods.method_name ";
	$sql .= "FROM ";
		$sql .= "ANNOTATIONS.annotations ";
	$sql .= "LEFT JOIN ANNOTATIONS.groups ON ANNOTATIONS.annotations.group_id = ANNOTATIONS.groups.id ";
	$sql .= "LEFT JOIN ANNOTATIONS.annotation_updates ON ANNOTATIONS.annotations.id = ANNOTATIONS.annotation_updates.annotation_id ";
	$sql .= "LEFT JOIN ANNOTATIONS.update_methods ON ANNOTATIONS.annotation_updates.update_method_id = ANNOTATIONS.update_methods.id ";
	$sql .= "WHERE ";
		$sql .= "ANNOTATIONS.annotation_updates.update_time = (SELECT MAX(ANNOTATIONS.annotation_updates.update_time) FROM ANNOTATIONS.annotation_updates WHERE ANNOTATIONS.annotation_updates.annotation_id = ANNOTATIONS.annotations.id)";
	// If the return all annotations flag hasn't been set, only return active annotations
	if (!isset($all_annotations) ) {
		$sql .= "AND ";
			$sql .= "ANNOTATIONS.annotations.active = '1'";
	}
	$sql .= "ORDER BY ";
		$sql .= "update_time DESC"; // Sort by updated most recently first
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	// Go through each result and save to an array
	while ($list = $statement->fetch()) {
		$annotations[$list["name"]]["description"] = $list["description"];
		$annotations[$list["name"]]["active"] = $list["active"];
		$annotations[$list["name"]]["group_name"] = $list["group_name"];
		$annotations[$list["name"]]["current_version"] = $list["version"];
		$annotations[$list["name"]]["current_version_update_time"] = $list["update_time"];
		$annotations[$list["name"]]["update_method"] = $list["method_name"];
	}
	
	return $annotations;
}

#############################################
# FETCH THE FULL ANNOTATION HISTORY FOR A GIVEN ANNOTATION NAME
#############################################

function fetch_annotation_info($annotation_name) {
	$annotation_information = array();
	
	$sql = "SELECT ";
		$sql .= "ANNOTATIONS.annotations.name, ";
		$sql .= "ANNOTATIONS.annotations.description, ";
		$sql .= "ANNOTATIONS.annotations.active, ";
		$sql .= "ANNOTATIONS.groups.group_name, ";
		$sql .= "ANNOTATIONS.annotation_updates.id as annotation_id, ";
		$sql .= "ANNOTATIONS.annotation_updates.version, ";
		$sql .= "ANNOTATIONS.annotation_updates.update_time, ";
		$sql .= "ANNOTATIONS.update_methods.method_name ";
	$sql .= "FROM ";
		$sql .= "ANNOTATIONS.annotations ";
	$sql .= "LEFT JOIN ANNOTATIONS.groups ON ANNOTATIONS.annotations.group_id = ANNOTATIONS.groups.id ";
	$sql .= "LEFT JOIN ANNOTATIONS.annotation_updates ON ANNOTATIONS.annotations.id = ANNOTATIONS.annotation_updates.annotation_id ";
	$sql .= "LEFT JOIN ANNOTATIONS.update_methods ON ANNOTATIONS.annotation_updates.update_method_id = ANNOTATIONS.update_methods.id ";
	$sql .= "WHERE ";
		$sql .= "ANNOTATIONS.annotations.name = ? ";
	$sql .= "ORDER BY ";
		$sql .= "ANNOTATIONS.annotation_updates.update_time";
	$sql .= ";";

	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$annotation_name]);
	
	// Go through each result and save to an array
	while ($list = $statement->fetch()) {
		$annotation_information["name"] = $list["name"];
		$annotation_information["description"] = $list["description"];
		$annotation_information["active"] = $list["active"];
		$annotation_information["group_name"] = $list["group_name"];
		
		$annotation_information["versions"][$list["annotation_id"]]["version"] = $list["version"];
		$annotation_information["versions"][$list["annotation_id"]]["update_time"] = $list["update_time"];
		$annotation_information["versions"][$list["annotation_id"]]["update_method"] = $list["method_name"];
	}
		
	return $annotation_information;
}

?>
