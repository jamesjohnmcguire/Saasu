<?php

require_once "Saasu.php";

// upon  a form submission
if (!empty($_POST))
{
	// fill with real aquired key
	$wsAccessKey = "xxxxx";
	$fileUid = "yyyyy";
	$accountUid = "zzzzz";

	// send saasu information
	$saasuObject = new Saasu($wsAccessKey, $fileUid);

	// Get contact
	$contactUid = $saasuObject->getContact($_POST['contactemail']);
	
	if (strlen(trim($contactUid)) == 0)
	{
		// if not, insert contact
		$contactUid = $saasuObject->insertContact($_POST['contactGivenName'],
			$_POST['contactFamilyName'], $_POST['contactemail'],
			$_POST['phone']);
		
		if (strlen(trim($contactUid)) == 0)
		{
			// error
			$saasuError = true;
		}
	}

	// create invoice
	$response = $saasuObject->insertInvoice($contactUid, $accountUid,
		trim($_POST['depart']), $_POST['totalCost'],
		$_POST['departTime'], $_POST['parking'],
		$_POST['contactmessage']);

	if (0 == strlen(trim($response)))
	{
			// error
	}
}

?>
