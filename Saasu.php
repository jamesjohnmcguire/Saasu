<?php

require_once "logging.php";

class Saasu
{
	private $baseUrl = "https://secure.saasu.com/webservices/rest/r1/";
	private $fileUid;
	private $log;
	private $resourceType = "Tasks";
	private $wsAccessKey;
	
	function __construct($wsAccessKey, $fileUid)
	{
		$this->wsAccessKey = $wsAccessKey;
		$this->fileUid = $fileUid;

		$this->log = new Logging();
		// set path and name of log file
		$time = @date('Y-m-d');

		$this->log->lfile("./logs/$time.txt");
	}

	function __destruct()
	{
		// close log file
		$this->log->lclose();
	}

	function getContact($emailAddress)
	{
		$xml = $this->getContactList();
		$contactUid = null;

		if ($xml)
		{
			foreach($xml->children()->children() as $contact)
			{
				if ($contact->emailAddress == $emailAddress)
				{
					$contactUid = $contact->contactUid;
					//var_dump($contact);
					//echo $contact->contactUid;
					//echo $contact->emailAddress;
					break;
				}
			}
		}
		return $contactUid;
	}
	
	function getContactList($rawXml = false)
	{
		$this->resourceType = "contactList";

		$response = $this->sendRequest(false, "");

		if (($rawXml == false) && ($response['errno'] == 0))
		{
			$xml = simplexml_load_string($response['content']);
		}
		else
		{
			$xml = $response['content'];
		}
		
		return $xml;
	}

	function insertContact($givenName, $familyName, $emailAddress, $phone)
	{
		$contactUid = null;
		$this->resourceType = "tasks";
		$xmlRequest = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<tasks>
	<insertContact>
		<contact uid=\"0\">
			<givenName><![CDATA[$givenName]]></givenName>
			<familyName><![CDATA[$familyName]]></familyName>
			<email><![CDATA[$emailAddress]]></email>
			<mainPhone><![CDATA[$phone]]></mainPhone>
			<isActive>true</isActive>
			<isPartner>false</isPartner>
			<isCustomer>true</isCustomer>
			<isSupplier>false</isSupplier>
		</contact>
	</insertContact>
</tasks>";

		$response = $this->sendRequest(true, $xmlRequest);

		if ($response['errno'] == 0)
		{
//var_dump($response['content']);
//var_dump($response);
			$xml = simplexml_load_string($response['content']);

			foreach($xml->insertContactResult[0]->attributes() as $name => $value)
			{
				if ($name == "insertedEntityUid")
				{
					$contactUid = $value;
					break;
				}
			}
		}

		return $contactUid;
	}

	// not being used yet
	function updateContact()
	{
		$this->resourceType = "tasks";
		//TOOD - finish later on, if needed
		$xmlRequest = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<tasks>
 <updateContact>
  <contact uid=\"22730\" lastUpdatedUid=\"AAAAAAAVA8A=\">
    <givenName><![CDATA[Mary]]></givenName>
    <familyName><![CDATA[Smith]]></familyName>
    <email><![CDATA[mary.smith@mrandmrssmith.com.au]]></email>
    <mainPhone><![CDATA[02 4444 4444]]></mainPhone>
    <isActive>true</isActive>
   </contact>
 </updateContact>
</tasks>";
	}

	function insertInvoice($contactUid, $accountUid, $dateDue, $totalAmount,
		$purchaseOrderNumberExtension, $description, $notes)
	{
		$invoiceUid = "";
		$this->resourceType = "Tasks";

		$today = date("Y-m-d");
		
		$formatDate = DateTime::createFromFormat('d/m/Y', $dateDue);
		$dateDue = $formatDate->format('Y-m-d'); 
  
		$xmlRequest = "<insertInvoice emailToContact=\"true\">
	<invoice uid=\"0\">
		<transactionType>S</transactionType>
		<date><![CDATA[$dateDue]]></date>
		<contactUid>$contactUid</contactUid>
		<folderUid>0</folderUid>
		<summary><![CDATA[$description]]></summary>
		<notes><![CDATA[$notes]]></notes>
		<requiresFollowUp>false</requiresFollowUp>
		<dueOrExpiryDate><![CDATA[$dateDue]]></dueOrExpiryDate>
		<layout>S</layout>
		<status>I</status>
		<invoiceNumber>&lt;Auto Number&gt;</invoiceNumber>
		<purchaseOrderNumber><![CDATA[$purchaseOrderNumberExtension]]></purchaseOrderNumber>
		<invoiceItems>
			<serviceInvoiceItem>
				<description><![CDATA[$description]]></description>
				<!--accountUid>1073198</accountUid-->
				<accountUid>$accountUid</accountUid>
				<taxCode>G1</taxCode>
				<totalAmountInclTax>$totalAmount</totalAmountInclTax>
			</serviceInvoiceItem>
		</invoiceItems>
		<isSent>false</isSent>
    </invoice>
    <createAsAdjustmentNote>false</createAsAdjustmentNote>
</insertInvoice>";

//echo "xml: $xmlRequest<br />\r\n";

		$response = $this->sendRequest(true, $xmlRequest);
//var_dump($response);

		if ($response['errno'] == 0)
		{
			$xml = simplexml_load_string($response['content']);
			$contactUid = null;

			if ($xml->errors)
			{
				$errors = "begin: ";
				foreach($xml->errors as $error)
				{
					$errors = $errors." ".$error->message;
				}

//				echo $error;
				$this->log->lwrite("problem with creating invoice, (xml-errors) errors: $errors");
				$output = var_export($response, true);
				$globalRequest = var_export($_REQUEST, true);
//				$browser = get_browser(null, true);
//				$browser_info = var_export($browser, true);
				$this->log->lwrite("xmlRequest: $xmlRequest");
				$this->log->lwrite("request: $globalRequest");
				$this->log->lwrite("response: $output");
//				$this->log->lwrite("browser: $browser_info");
				$message = "problem with creating invoice:\n".
					"errors: (xml-errors) $errors\n".
//					"browser: $browser_info\n".
					"xmlRequest: $xmlRequest\n".
					"request: $globalRequest\n".
					"response: $output\n";
				mail("james@methodit.co.jp, jamesjohnmcguire@gmail.com, daniel@methodit.co.jp",
					"Sassu: problem with creating invoice", $message,
					"From: Aussie Airport Parking <bookings@aussieairportparking.com.au>\nX-Mailer: PHP/" . phpversion());
			}
			else
			{
				foreach($xml->insertInvoiceResult[0]->attributes() as $name => $value)
				{
					if ($name == "insertedEntityUid")
					{
						$invoiceUid = $value;
						break;
					}
				}
			}
		}
		else
		{
			$this->log->lwrite("problem with creating invoice");
			$output = var_export($response, true);
			$globalRequest = var_export($_REQUEST, true);
//				$browser = get_browser(null, true);
//				$browser_info = var_export($browser, true);
			$this->log->lwrite("xmlRequest: $xmlRequest");
			$this->log->lwrite("request: $globalRequest");
			$this->log->lwrite("response: $output");
//				$this->log->lwrite("browser: $browser_info");
			$message = "problem with creating invoice:\n".
				"errors: (xml-errors) $errors\n".
//					"browser: $browser_info\n".
				"xmlRequest: $xmlRequest\n".
				"request: $globalRequest\n".
				"response: $output\n";
			mail("james@methodit.co.jp, jamesjohnmcguire@gmail.com, daniel@methodit.co.jp",
				"Sassu: problem with creating invoice", $message,
				"From: Aussie Airport Parking <bookings@aussieairportparking.com.au>\nX-Mailer: PHP/" . phpversion());
		}

		return $invoiceUid;
	}

	private function makeUrl()
	{
		return "$this->baseUrl$this->resourceType?wsaccesskey=$this->wsAccessKey&fileuid=$this->fileUid";
	}

	private function sendRequest($isPost, $fields)
	{
		$url = $this->makeUrl();

		$curlObject	= curl_init($url);

		if (true == $isPost)
		{
			curl_setopt($curlObject, CURLOPT_POST, 1);
			curl_setopt($curlObject, CURLOPT_POSTFIELDS, $fields);
		}

		curl_setopt($curlObject, CURLOPT_RETURNTRANSFER,	true);		// return web page
		curl_setopt($curlObject, CURLOPT_HEADER, 			false);		// don't return headers
		curl_setopt($curlObject, CURLOPT_FOLLOWLOCATION, 	true);		// follow redirects
		curl_setopt($curlObject, CURLOPT_ENCODING, 			"");		// handle all encodings
		curl_setopt($curlObject, CURLOPT_USERAGENT, 		"Saasu Client");	// who am i
		curl_setopt($curlObject, CURLOPT_AUTOREFERER, 		true);		// set referer on redirect
		curl_setopt($curlObject, CURLOPT_CONNECTTIMEOUT, 	120);		// timeout on connect
		curl_setopt($curlObject, CURLOPT_TIMEOUT, 			120);		// timeout on response
		curl_setopt($curlObject, CURLOPT_MAXREDIRS, 		10);		// stop after 10 redirects
		//curl_setopt($curlObject, CURLOPT_HTTPHEADER, 		Array("Content-Type: text/xml"));

		//$headers = array(
		//    "Content-type: text/xml"
		//    ,"Connection: close"
		//);
		//		curl_setopt($curlObject, CURLOPT_HTTPHEADER, 		$headers);
		
		curl_setopt ($curlObject, CURLOPT_SSL_VERIFYPEER, TRUE); 
//		curl_setopt ($curlObject, CURLOPT_CAINFO, "c:/Util/Xampp/cacert.pem");		
		curl_setopt ($curlObject, CURLINFO_HEADER_OUT, TRUE);

		$content = curl_exec( $curlObject);

		$err     = curl_errno($curlObject);
		$errmsg  = curl_error($curlObject);
		$header  = curl_getinfo($curlObject);

		curl_close($curlObject);

		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['content'] = $content;
		
		//echo "output: <br\>\r\n";
		//var_dump($header);

		return $header;
	}
}	// end class
?>
