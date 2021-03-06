<?php 

require_once WP_PLUGIN_DIR . '/opendocs/opendocs/opendocs-data-interface.php';
require_once WP_PLUGIN_DIR . '/opendocs/opendocs/opendocs-community.php';
require_once WP_PLUGIN_DIR . '/opendocs/opendocs/cms/opendocs-wordpress.php';
require_once WP_PLUGIN_DIR . '/opendocs/lib/vendor/autoload.php';

use \Curl\MultiCurl;
use \Curl\Curl;

/**
 * The Data Wrapper Class (XML based)
 *
 * @link       https://opendocs.ids.ac.uk
 * @since      1.0.0
 *
 * @package    XML_IDocs_Query
 */

class XML_IDocs_Query implements IDocs_Query_Interface {
    
	/**
 	* API main URL.
 	*
 	* @since 1.0.0
 	* @var string $APIUrl Stores API main URL.
 	*/
    private $APIUrl = ''; // Stores API main URL
	/**
 	* Item Handle IDs.
 	*
 	* @since 1.0.0
 	* @var array of item ids $itemHandles Stores Item Handle IDs
 	*/
	private $itemHandles = []; // Stores Item Handle IDs
	/**
 	* item IDs List.
 	*
 	* @since 1.0.0
 	* @var array of item IDs $itemIDs Stores item IDs
 	*/
	private $itemIDs = []; // Item IDs
	/**
 	* post type info.
 	*
 	* @since 1.0.0
 	* @var string selected post type info
 	*/
	private $postTypeInfo = '';
	/**
 	* Collection ID
 	*
 	* @since 1.0.0
 	* @var int Collection ID
 	*/
	private	$collID = '';
	/**
 	* Field Mapping
 	*
 	* @since 1.0.0
 	* @var array field mapping (stores array of ACF id and API field ID)
 	*/
	private	$fieldMapping = [];
	/**
 	* Multi Curl resource
 	*
 	* @since 1.0.0
 	* @var object Multi Curl object
 	*/
	private $mh = null;
	/**
 	* Existing Item Count
 	*
 	* @since 1.0.0
 	* @var int stores existing item count
 	*/
	private $existingItemCount = 0;
	/**
 	* Item Count
 	*
 	* @since 1.0.0
 	* @var int stores number of items to import
 	*/
	private $itemCount = 0;
	/**
 	* Imported Item IDs
 	*
 	* @since 1.0.0
 	* @var array stores list of imported item IDs. 
 	*/
	private $insertedItemIDs = [];
	/**
 	* Existing Item List
 	*
 	* @since 1.0.0
 	* @var array stores list of existing items
 	*/
	private $existingItems = [];
	/**
 	* Requests 
 	*
 	* @since 1.0.0
 	* @var array list of request URLs
 	*/
	private $requests = [];
	private $isCRON = false;
	private $cronID = -1;
	    
	
    public function __construct($APIUrl) {
        $this->APIUrl = $APIUrl;
    }
    
	/**
 	* Get Top Communities list.
 	*
 	* Retrieves top communities from OpenDocs API and stores in transient cache. 
 	*
 	* @since 1.0.0
 	*
 	*
 	* @return array list of OpenDocs_Community objects .
	*/
    public function getTopCommunities() {
        $topCommunitiesDom = $this->getXMLDomDoc( $this->APIUrl . '/communities/top-communities' );
		$communities = array();
		if( $topCommunitiesDom !== -1 ) : 
        	$topCommunities = $topCommunitiesDom->getElementsByTagName( 'community' );
        	foreach( $topCommunities as $community ) : 
        		$name = $community->getElementsByTagName( 'name' );
        		$name = $name->item(0)->nodeValue;
        		$id = $community->getElementsByTagName( 'id' );
        		$id = $id->item(0)->nodeValue;
        		$count = $community->getElementsByTagName( 'countItems' );
        		$count = $count->item(0)->nodeValue;
       			$type = $community->getElementsByTagName( 'type' );
        		$type = $type->item(0)->nodeValue;
       	 		$topCommunity = new OpenDocs_Community( $id, $name, $count, $type );
        		$communities[] = $topCommunity;
			endforeach;
		endif;
	return $communities;
        
    }
	
	/**
 	* Sub Communities of community
 	*
 	* Retrieves sub communities from a community using OpenDocs API and stores in transient cache. 
 	*
 	* @since 1.0.0
 	*
 	* @param int $communityID Community ID to retrieve sub communities
	*
 	* @return array List of communities (community ID, name, item count, type)
	*/
    public function getSubCommunities($communityID) {
    	$topCommunitiesDom = $this->getXMLDomDoc( $this->APIUrl . '/communities/' . $communityID . '/communities/?offset=-1' );
		$communities = array();
		if( $topCommunitiesDom !== -1 ) : 
			$topCommunities = $topCommunitiesDom->getElementsByTagName( 'community' );
			foreach( $topCommunities as $community ) : 
				$name = $community->getElementsByTagName( 'name' );
        		$name = $name->item(0)->nodeValue;
        		$id = $community->getElementsByTagName( 'id' );
        		$id = $id->item(0)->nodeValue;
        		$count = $community->getElementsByTagName( 'countItems' );
        		$count = $count->item(0)->nodeValue;
        		$type = $community->getElementsByTagName( 'type' );
        		$type = $type->item(0)->nodeValue;
       	 		$topCommunity = array( 'id' => $id, 'name' => $name, 'count' => $count, 'type' => $type );
        		$communities[] = $topCommunity;
			endforeach;
		endif;
	return $communities;
        
    }
	
	/**
 	* Collections In Community
 	*
 	* Retrieves collections in a community by community ID 
 	*
 	* @since 1.0.0
 	*
 	* @param int $communityID Community ID to retrieve collection
	*
 	* @return array List of collections (community ID, name, item count, type)
	*/
    public function getCollectionsByCommunity($communityID) { 
    	$collectionsDom = $this->getXMLDomDoc( $this->APIUrl . '/communities/' . $communityID . '/collections/?offset=-1' );
		$collections = array();
		if( $collectionsDom !== -1 ) : 
			$collectionsNodes = $collectionsDom->getElementsByTagName( 'collection' );
			foreach( $collectionsNodes as $collection ) : 
				$name = $collection->getElementsByTagName( 'name' );
        		$name = $name->item(0)->nodeValue;
        		$id = $collection->getElementsByTagName( 'id' );
        		$id = $id->item(0)->nodeValue;
        		$count = $collection->getElementsByTagName( 'numberItems' );
        		$count = $count->item(0)->nodeValue;
	       		$type = $collection->getElementsByTagName( 'type' );
        		$type = $type->item(0)->nodeValue;
       	 		$collectionObj = array( 'id' => $id, 'name' => $name, 'count' => $count, 'type' => $type );
        		$collections[] = $collectionObj;
			endforeach;
		endif;
	return $collections;
    }
	
	/**
 	* Items In Collection
 	*
 	* Retrieves items in a collection by collection ID
 	*
 	* @since 1.0.0
 	*
 	* @param int $communityID Community ID to retrieve collection
	*
 	* @return array List of items (item ID, name, collection id, item imported or not)
	*/
    public function getItemsInCollection($collectionIDs) {
    	$items = array();
		$requests = array();
		$itemDOMs = array();
    	foreach( $collectionIDs as $collectionID ) : 
    		$itemsInCollectionCount = $collectionID[0];
			$existingItems = $collectionID[2];
    		$totalPages = ceil( $itemsInCollectionCount / 100 );
    		for($i = 0;$i < $totalPages;$i++) {
		        $requests[$i] = $this->APIUrl . '/collections/' . $collectionID[1] . '/items/?offset=' . $i * 100;
			}
			if( $totalPages >= 2 ) : 
				$itemDOMs = $this->getXMLDomDocMulti( $requests, true );
			else : 
				$itemDOMs = $this->getXMLDomDocMulti( $requests, true );
			endif;
			for($i = 0;$i < count( $itemDOMs );$i++) {
				$itemNodes = $itemDOMs[ $i ]->getElementsByTagName( 'item' );
				foreach( $itemNodes as $item ) : 
					$id = $item->getElementsByTagName( 'id' );
					$id = $id->item(0)->nodeValue;
						$name = $item->getElementsByTagName( 'name' );
        				$name = $name->item(0)->nodeValue;
						if( in_array( $id, $existingItems ) ) : 
		       	 			$itemObj = array( 'id' => $id, 'name' => $name, 'collectionID' => $collectionID[1], 'existing' => 1 );
						else : 
							$itemObj = array( 'id' => $id, 'name' => $name, 'collectionID' => $collectionID[1], 'existing' => 0 );
						endif;
        				$items[] = $itemObj;
				endforeach;
			} 
		endforeach;
	return $items;
    }
	
	/**
 	* Item IDs in a collection
 	*
 	* Retrieves items IDs in a collection by collection ID
 	*
 	* @since 1.0.0
 	*
 	* @param int $communityID Community ID to retrieve collection
	*
 	* @return array List of items IDs
	*/
	public function getItemIDsInCollection($collectionIDs) {
		$items = array();
		$requests = array();
		$itemDOMs = array();
    	foreach( $collectionIDs as $collectionID ) : 
    		$itemsInCollectionCount = $collectionID[0];
    		$totalPages = ceil( $itemsInCollectionCount / 100 );
    		for($i = 0;$i < $totalPages;$i++) {
		        $requests[$i] = $this->APIUrl . '/collections/' . $collectionID[1] . '/items/?offset=' . $i * 100;
			} 
			if( $totalPages >= 2 ) : 
				$itemDOMs = $this->getXMLDomDocMulti( $requests, true );
			else : 
				$itemDOMs = $this->getXMLDomDocMulti( $requests );
			endif;
			for($i = 0;$i < count( $itemDOMs );$i++) {
				$itemNodes = $itemDOMs[ $i ]->getElementsByTagName( 'item' );
				foreach( $itemNodes as $item ) : 
	        		$id = $item->getElementsByTagName( 'id' );
	        		$id = $id->item(0)->nodeValue;
        			$items[] = $id;
				endforeach;
			}
		endforeach;
	return $items;
    }
	
	/**
 	* Starts the field retrieval process
 	*
 	* Starts import process ->
	* 1. Retrieves all field data according to user selection
	* 2. Sends field mapping data to CMS class to create posts and add data. 
 	*
 	* @since 1.0.0
 	*
 	* @param class $itemInfo Class containing item related properties: list of items to import, item handles list, existing items
	* @param class $mappingInfo Class containing field mapping info: post type info, collection ID, field mapping array
	* @param class $isCRON Class containing CRON info: is the current running job a CRON? CRON Job ID
	*
 	* @return array List of inserted Post IDs (specific to Wordpress)
	*/
    public function getItems($itemInfo, $mappingInfo, $isCRON) {
    	
		
		
		$fieldValuesList = [];
		$requests = [];
		$toInsertedMapping = [];
		
		// Setup class properties
		$this->itemHandles = $itemInfo->itemHandles;
		$this->itemIDs = $itemInfo->itemsList;
		$this->postTypeInfo = $mappingInfo->postTypeInfo;
		$this->collID = $mappingInfo->collID;
		$this->fieldMapping = $mappingInfo->mappingArray;
		$this->existingItems = $itemInfo->existingItems;
		$this->itemCount = count($this->itemIDs);
		$this->isCRON = $isCRON->isCRON;
		$this->cronID = $isCRON->cronID;
		
		// Setup field mapping array if it's not CRON
		if( $this->isCRON == false ) : 
			$this->cronID = $isCRON->insertedcronID;
		endif;
		
		// Setup requests array for data retrieval
		foreach($this->itemIDs as $itemID) : 
			$requests[] = array( 'itemID' => $itemID, 'url' => $this->APIUrl . '/items/' . $itemID . '/metadata/' );
		endforeach;
		$this->requests = $requests;

		// Start data retrieval
		$insertedPostIDs = $this->getXMLDomDocMultiValues( $this->itemIDs, $requests );
		return $insertedPostIDs;
    	
    }
	
	/**
 	* Gets items Info 
 	*
 	* Returns array of items info
 	*
 	* @since 1.0.0
 	*
 	* @param array $itemIDsList Array of itemIDs to get info
	*
 	* @return array List of items (item name, handle link)
	*/
	public function getItemsInfo($itemIDsList) {
		$requests = [];
		$results = [];
		foreach($itemIDsList as $itemID) : 
			$requests[] = $this->APIUrl . '/items/' . $itemID;
		endforeach;
		$itemsDOMList = $this->getXMLDomDocMulti($requests);
		for($i = 0;$i < count( $itemsDOMList );$i++) {
			$itemNodes = $itemsDOMList[ $i ]->getElementsByTagName( 'item' );
			foreach( $itemNodes as $item ) : 
	        	$name = $item->getElementsByTagName( 'name' );
	        	$name = $name->item(0)->nodeValue;
				$id = $item->getElementsByTagName( 'id' );
	        	$id = $id->item(0)->nodeValue;
				$handleID = $item->getElementsByTagName( 'handle' );
	        	$handleID = $handleID->item(0)->nodeValue;
        		$results[$id] = array('name' => $name, 'handle' => 'http://opendocs.ids.ac.uk/opendocs/handle/' . $handleID );
			endforeach;
		}
		return $results;
	}
	
	/**
 	* Retrieve item data
 	*
 	* Import each item data
 	*
 	* @since 1.0.0
 	*
 	* @param int $itemID Item ID
	* @param string $xmlContent XML Content to get data from
	* @param array $errorItems contains list item Ids which are failed
	*
 	* @return int inserted post ID (Wordpress ID)
	*/
	private function retrieveValues($itemID, $xmlContent, $errorItems) {
		$fieldValues = [];
		
		// Load XML Content into DomDocument and iniatialize DOM XPath
		$itemDom = new DOMDocument();
        $itemDom->loadXML($xmlContent);
    	$itemXPath = new DOMXPath($itemDom);
		
		$postTypeInfo = $this->postTypeInfo;
		$fieldMapping = $this->fieldMapping;
		$itemHandles = $this->itemHandles;
		$importedCount = count($this->insertedItemIDs);
		$existingItems = $this->existingItems;
		$itemCount = $this->itemCount;
		$itemIDs = $this->itemIDs;
		
		$wp_class = new Wordpress_IDocs();
		
		//Create field mapping array and retrieve meta data. 
    	foreach( $fieldMapping as $mapping ) : 
    		if( $mapping[1] == 'full_text_url' || $mapping[1] == 'full_text_type' || $mapping[1] == 'full_text_size' ) : 
				if( ! array_key_exists( 'field_type', $mapping ) ) : 
    				$fieldValues[] = array( 'field_id' => $mapping[0], 'field_name' => $mapping[1], 'field_value' => '', 'acf_name' => $mapping[3] );
				else : 
						$fieldValues[] = array( 'field_id' => $mapping[0], 'field_name' => $mapping[1], 'field_value' => '', 'sub_fields' => $mapping['sub_fields'], 'field_type' => $mapping['field_type'], 'acf_name' => $mapping[3], 'sub_field_names' => $mapping[4] );
				endif;
    		else : 
			$mappedNodes = $itemXPath->query("//metadataentry[key[contains(., '" . $mapping[1] . "')]]/value");
	    	foreach( $mappedNodes as $mappedNode ) : 
    			$fieldValue = $mappedNode->nodeValue;
		
				$parentNode = $mappedNode->parentNode;
				$lang = 'N/A';
				$langEntries = $itemXPath->query("language", $parentNode);
				foreach($langEntries as $langEntry) : 
					if( empty($langEntry->nodeValue) ) : 
						$lang = 'N/A';
					else : 
						$lang = $langEntry->nodeValue;
					endif;  
				endforeach;
		
					
					if( ! array_key_exists( 'field_type', $mapping ) ) : 
						$fieldValues[] = array( 'field_id' => $mapping[0], 'field_name' => $mapping[1], 'field_value' => $fieldValue, 'acf_name' => $mapping[3], 'lang' => $lang );
    				else : 
						if( $mapping['field_type'] == 'repeater' ) : 
    						$fieldValues[] = array( 'field_id' => $mapping[0], 'field_name' => $mapping[1], 'field_value' => $fieldValue, 'sub_fields' => $mapping['sub_fields'], 'field_type' => $mapping['field_type'], 'acf_name' => $mapping[3], 'sub_field_names' => $mapping[4], 'lang' => $lang );
						else : 
							$fieldValues[] = array( 'field_id' => $mapping[0], 'field_name' => $mapping[1], 'field_value' => $fieldValue, 'field_type' => $mapping['field_type'], 'lang' => $lang );
						endif;
	    			endif;
	    		endforeach;
    		endif;
    	endforeach;
		
		// Create Wordpress Post with retrieved meta data. 
		$insertedPostID = $wp_class->insertPost($itemID, $itemHandles[$itemID], $fieldValues, $postTypeInfo, $itemCount, $this->insertedItemIDs, $errorItems, $this->itemIDs, $this->itemHandles, $existingItems, $this->isCRON, $this->cronID );
		$this->insertedItemIDs[$itemID] = $insertedPostID;
		return $insertedPostID;
	}
	
	private function retrieveValuesCRON($itemID, $xmlContent) {
		$fieldValues = [];
		$itemDom = new DOMDocument();
        $itemDom->loadXML($xmlContent);
    	$itemXPath = new DOMXPath($itemDom);
		
		$postTypeInfo = $this->postTypeInfo;
		$fieldMapping = $this->fieldMapping;
		$itemHandles = $this->itemHandles;
		$importedCount = count($this->insertedItemIDs);
		$existingItems = $this->existingItems;
		$itemCount = $this->itemCount;
		$itemIDs = $this->itemIDs;
		
		$wp_class = new Wordpress_IDocs();
    	foreach( $fieldMapping as $mapping ) : 
    		if( $mapping['field_name'] == 'full_text_url' || $mapping['field_name'] == 'full_text_type' || $mapping['field_name'] == 'full_text_size' ) : 
				if( ! array_key_exists( 'field_type', $mapping ) ) : 
    				$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => '', 'acf_name' => $mapping['acf_name'] );
				else : 
						$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => '', 'sub_fields' => $mapping['sub_fields'], 'field_type' => $mapping['field_type'], 'acf_name' => $mapping['acf_name'], 'sub_field_names' => $mapping['sub_field_names'] );
				endif;
    		else : 
			$mappedNodes = $itemXPath->query("//metadataentry[key[contains(., '" . $mapping['field_name'] . "')]]/value");
	    	foreach( $mappedNodes as $mappedNode ) : 
    			$fieldValue = $mappedNode->nodeValue;
		
				$parentNode = $mappedNode->parentNode;
				$lang = 'N/A';
				$langEntries = $itemXPath->query("language", $parentNode);
				if( $langEntries->length > 0 && !empty( $langEntries->item(0)->nodeValue ) ) : 
					$lang = $langEntries->item(0)->nodeValue;
				endif; 
					if( ! array_key_exists( 'field_type', $mapping ) ) : 
						$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => $fieldValue, 'acf_name' => $mapping['acf_name'], 'lang' => $lang );
    				else : 
						if( $mapping['field_type'] == 'repeater' ) : 
    						$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => $fieldValue, 'sub_fields' => $mapping['sub_fields'], 'field_type' => $mapping['field_type'], 'acf_name' => $mapping['acf_name'], 'sub_field_names' => $mapping['sub_field_names'], 'lang' => $lang );
						else : 
							$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => $fieldValue, 'field_type' => $mapping['field_type'], 'lang' => $lang );
						endif;
	    			endif;
	    		endforeach;
    		endif;
    	endforeach;
		$insertedPostID = $wp_class->insertPost($itemID, $itemHandles[$itemID], $fieldValues, $postTypeInfo, $itemCount, $this->insertedItemIDs, $errorItems, $this->itemIDs, $this->itemHandles, $existingItems, $this->isCRON, $this->cronID );
		$this->insertedItemIDs[$itemID] = $insertedPostID;
		return $insertedPostID;
	}
	
	/**
 	* Retrieve item's File info
 	*
 	* Get each item's URL info and updates Wordpress Post Meta
 	*
 	* @since 1.0.0
 	*
 	* @param array $importedItems List of Imported Wordpress Post IDs
	* @param array $fieldMapping Array of fields to retrieve data
	* @param array $itemIDs List of itemd IDs
	* @param array $itemHandles List of item Handle IDs
	*
 	* @return int inserted post ID (Wordpress ID)
	*/
    public function getItemFiles($importedItems, $fieldMapping, $itemIDs, $itemHandles ) {
		
		$fieldValues = [];
		
		foreach( $fieldMapping as $mapping ) : 
    		if( $mapping['field_name'] == 'full_text_url' || $mapping['field_name'] == 'full_text_type' || $mapping['field_name'] == 'full_text_size' ) : 
				if( ! array_key_exists( 'field_type', $mapping ) ) : 
    				$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => '', 'acf_name' => $mapping['acf_name'] );
				else : 
						$fieldValues[] = array( 'field_id' => $mapping['field_id'], 'field_name' => $mapping['field_name'], 'field_value' => '', 'sub_fields' => $mapping['sub_fields'], 'field_type' => $mapping['field_type'], 'acf_name' => $mapping['acf_name'], 'sub_field_names' => $mapping['sub_field_names'] );
				endif;
			endif;
		endforeach;
		$itemFiles = $this->getXMLDomDocMultiGetURL($importedItems, $fieldValues, $itemIDs, $itemHandles);
		return $itemFiles;
    }
		
	/**
 	* Get item Handles
 	*
 	* Get list of item handles 
 	*
 	* @since 1.0.0
 	*
 	* @param array $itemIDs List of item IDs
	*
 	* @return int inserted post ID (Wordpress ID)
	*/
    public function getItemHandle( $itemIDs ) {
		$itemHandles = [];
		$requests = [];
		foreach($itemIDs as $itemID) : 
			$requests[] = $this->APIUrl . '/items/' . $itemID;
		endforeach;
    	$itemHandlesDom = $this->getXMLDomDocMulti( $requests );
		
		foreach( $itemHandlesDom as $itemHandleDom ) : 
			if( $itemHandleDom !== -1 ) : 
    			$itemHandleNodes = $itemHandleDom->getElementsByTagName( 'item' );
				foreach( $itemHandleNodes as $item ) : 
	        		$itemHandle = $item->getElementsByTagName( 'handle' );
	        		$itemHandle = $itemHandle->item(0)->nodeValue;
					$itemID = $item->getElementsByTagName( 'id' );
	        		$itemID = $itemID->item(0)->nodeValue;
					$itemHandles[$itemID] = $itemHandle;
				endforeach;
			endif;
		endforeach;
		$this->itemHandles = $itemHandles;
	return $itemHandles;
    }
	
	/**
 	* Get collection metadata
 	*
 	* Get list of unique keys of collection metadata 
 	*
 	* @since 1.0.0
 	*
 	* @param into $collectionID 
	*
 	* @return array Unique metadata keys from all items in the collection
	*/
	public function getCollectionMetaDate($collectionID) {
		$itemCount = $this->getItemCountInCollection($collectionID);
		$itemIDs = $this->getItemIDsInCollection( array( array( $itemCount, $collectionID ) ) );
		$metaData = [];
		$requests = [];
		
		for($i = 0;$i < $itemCount;$i++) {
		        $requests[$i] = $this->APIUrl . '/items/' . $itemIDs[0] . '/metadata/';
		} 
		$itemDOMs = $this->getXMLDomDocMulti( $requests );
		
		foreach( $itemDOMs as $itemDOM ) : 
			if( $itemDOM !== -1 ) : 
				$itemXPath = new DOMXPath($itemDOM);
				$itemMetaQuery = $itemXPath->query("//metadataentry/key");
				foreach( $itemMetaQuery as $itemMeta ) : 
					$metaData[] = $itemMeta->nodeValue;
				endforeach;
			endif;
		endforeach;
		return array_unique( $metaData );
		
	}
	
	/**
 	* Get item count of collection
 	*
 	* Get item count in a collection 
 	*
 	* @since 1.0.0
 	*
 	* @param int $collectionID 
	*
 	* @return int item count
	*/
    public function getItemCountInCollection($collectionID) {
    	$itemCount = 0;
    	$collectionDOM = $this->getXMLDomDoc( $this->APIUrl . '/collections/' . $collectionID );
		if( $collectionDOM !== -1 ) : 
    		$collection = $collectionDOM->getElementsByTagName('collection');
    		foreach( $collection as $coll ) : 
    			$itemCountNode = $coll->getElementsByTagName('numberItems');
    			$itemCount = $itemCountNode->item(0)->nodeValue;
    		endforeach;
		endif;
    	return $itemCount;
    	
    	
    }
	
	/**
 	* Returns XML DomDocument from URL
 	*
 	* Initializes Curl to retrieve URL content and returns XML DomDocument 
 	*
 	* @since 1.0.0
 	*
 	* @param string $url URL to retrieve XML Content from
	*
 	* @return class $doc XML DomDocument if content retrieved succesfully or -1
	*/
    private function getXMLDomDoc( $url ) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 ); 
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $ch, CURLOPT_ENCODING, '' );
		curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Accept: application/xml' ) );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 0 ); 
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 ); 
		$response = curl_exec($ch);

		curl_close($ch);
		usleep(1000000);
		if( $response !== false ) : 
        	$doc = new DOMDocument();
			$doc->preserveWhiteSpace = false;
        	$doc->loadXML($response);
			return $doc;
		else : 
			return -1;
		endif;
        
    }
	
	/**
 	* Get list of XML DomDocuments
 	*
 	* Get list of XML DomDocument items with content from supplied requests 
 	*
 	* @since 1.0.0
 	*
 	* @param array $requests List of URLs to retrieve XML Content
	*
 	* @return array List of XML Domdocument items
	*/
	private function getXMLDomDocMulti( $requests ) {
        $mh = curl_multi_init();
		$result = array();
		for( $i = 0; $i < count( $requests ); $i++ ) {
			$curls[$i] = curl_init();
			curl_setopt( $curls[$i], CURLOPT_URL, $requests[$i] );
        	curl_setopt( $curls[$i], CURLOPT_RETURNTRANSFER, 1 );
        	curl_setopt( $curls[$i], CURLOPT_SSL_VERIFYPEER, 0 ); 
        	curl_setopt( $curls[$i], CURLOPT_SSL_VERIFYHOST, 0 );
        	curl_setopt( $curls[$i], CURLOPT_ENCODING, '' );
			//curl_setopt( $curls[$i], CURLOPT_LOW_SPEED_LIMIT, 1 );
			//curl_setopt( $curls[$i], CURLOPT_LOW_SPEED_TIME , 10 ); 
			curl_setopt( $curls[$i], CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)" );
        	curl_setopt( $curls[$i], CURLOPT_HTTPHEADER, array( 'Accept: application/xml' ) );
			curl_setopt( $curls[$i], CURLOPT_CONNECTTIMEOUT, 15 ); 
        	curl_setopt( $curls[$i], CURLOPT_TIMEOUT, 30 ); 
			curl_multi_add_handle( $mh, $curls[$i] );
		}
		do {
			$execReturnValue = curl_multi_exec( $mh, $running );
		} while ( $execReturnValue == CURLM_CALL_MULTI_PERFORM );
		
		while ( $running && $execReturnValue == CURLM_OK ) {
			$numReady = curl_multi_select( $mh );
			if ($numReady == -1) {
                usleep(100);
            }
			do {
				$execReturnValue = curl_multi_exec( $mh, $running );
			} while ( $execReturnValue == CURLM_CALL_MULTI_PERFORM );
		}
		
		foreach( $curls as $id => $c ) {
			$xmlContent = curl_multi_getcontent( $c );
			$doc = new DOMDocument();
        	$doc->loadXML($xmlContent);
			$result[] = $doc;
			curl_multi_remove_handle( $mh, $c );
		}
		curl_multi_close( $mh );
		return $result;
    }
	
	/**
 	* Retrieves each items file info
 	*
 	* Initializes MultiCurl to retrieve file info of items and adds back as Wordpress Post metadata
 	*
 	* @since 1.0.0
 	*
 	* @param array $importedItems List of imported Wordpress Post IDs
	* @param array $fileFields File Mapping array
	* @param array $itemIDs Item ID List
	* @param array $itemHandles Item Handle List
	*
	*/
	private function getXMLDomDocMultiGetURL( $importedItems, $fileFields, $itemIDs, $itemHandles ) {
		$multi_curl = new MultiCurl();
		$multi_curl->setHeader('Accept', 'application/xml');
		$multi_curl->setConcurrency(50);
		$multi_curl->setConnectTimeout(15);
		$multi_curl->setTimeout(15);
		$multi_curl->setXmlDecoder(false);
		$multi_curl->setOpt(CURLOPT_HEADER , FALSE);
		$multi_curl->setOpt(CURLOPT_NOBODY , FALSE);
		$itemFile = '';
		$requests = [];
		$itemFiles = [];
		$fieldMapping = $this->fieldMapping;
		
		// Add each item's file to MultiCurl Get
		foreach( $itemIDs as $itemID ) {
			$itemHandle = $itemHandles[$itemID];
			$multi_curl->addGet( 'https://opendocs.ids.ac.uk/opendocs/bitstream/handle/' . $itemHandle . '/' . $itemID . '?sequence=1' );
			$requests[$itemID] = 'https://opendocs.ids.ac.uk/opendocs/bitstream/handle/' . $itemHandle . '/' . $itemID . '?sequence=1';
		}
		// If retrieval succeeds, do this
		$multi_curl->success(function ($instance) use ($requests, $importedItems, $itemHandles, $fileFields, &$itemFile) {
    		$url = $instance->url;
    		$fileLength = $instance->responseHeaders['Content-Length'];
			$fileType = $instance->responseHeaders['Content-Type'];
			$fileLanguage = $instance->responseHeaders['Content-Language'];
			$fieldValues = [];
			
			if( empty( $fileLanguage ) ) : 
				$fileLanguage = 'N/A';
			endif;
			$itemID = array_search( $url, $requests );
			$itemHandle = $itemHandles[$itemID]; 
			$itemFile = array( $itemID, $url, $itemHandle, $fileType, $fileLength, $fileLanguage );
			
			$insertedPostID = $importedItems[$itemID];	
			$wp_class = new Wordpress_IDocs();
			$wp_class->updatePostDownloads($insertedPostID, $fileFields, $itemFile);
		});
		// If failed, do this
		$multi_curl->error(function ($instance) use ($requests, $importedItems, $itemHandles, $fileFields, &$itemFile) {
			$url = $instance->url;
			$itemID = array_search( $url, $requests );
			$itemHandle = $itemHandles[$itemID]; 
			
			$fileName = 'https://opendocs.ids.ac.uk/opendocs/bitstream/handle/' . $itemHandle . '/' . $itemID . '?sequence=1';
			$fileType = '';
			$fileSize = 0;
			$fileLanguage = 'en';
			
			$itemFile = array( $itemID, $fileName, $itemHandle, '', 0, 'en'); 
			
			$insertedPostID = $importedItems[$itemID];	
			$wp_class = new Wordpress_IDocs();
			$wp_class->updatePostDownloads($insertedPostID, $fileFields, $itemFile);
			
		});
		$multi_curl->complete(function($instance) use ($requests, $importedItems, &$itemFiles, &$itemFile) {
			$url = $instance->url;
			$itemID = array_search( $url, $requests );
			$insertedPostID = $importedItems[$itemID];
    		$itemFiles[$insertedPostID] = $itemFile;
		});
		$multi_curl->start();
		return $itemFiles;
    }
		
	private function getXMLDomDocMultiValues( $itemIDs, $requests ) {
		$multi_curl = new MultiCurl();
		$multi_curl->setHeader('Accept', 'application/xml');
		$multi_curl->setConcurrency(100);
		$multi_curl->setConnectTimeout(15);
		$multi_curl->setTimeout(15);
		$multi_curl->setXmlDecoder(false);
		$insertedPostID = '';
		$insertedPostIDs = [];
		$errorItems = [];
		foreach( $requests as $key => $req ) {
   			$multi_curl->addGet($req['url']);
		}
		$multi_curl->success(function ($instance) use(&$insertedPostID) {
    		$url = $instance->url;
    		$xmlContent = $instance->response;
			$itemID = $this->findItemIDByURL($url);
			if( $this->isCRON == false ) : 
				$insertedPostID = $this->retrieveValues($itemID, $xmlContent, $errorItems);
			else : 
				$insertedPostID = $this->retrieveValuesCRON($itemID, $xmlContent, $errorItems);
			endif;
		});
		$multi_curl->error(function ($instance) {
			$itemID = $this->findItemIDByURL($url);
			$errorItems[] = $itemID;
		});
		$multi_curl->complete(function ($instance) use(&$insertedPostIDs, &$insertedPostID) {
    		$insertedPostIDs[] = $insertedPostID;
		});
		$multi_curl->start();
		return $insertedPostIDs;
    }
	private function findItemIDByURL($url) {
		foreach( $this->requests as $req ) {
			if( $req['url'] == $url ) {
				return $req['itemID'];	
			}
		}
		return 0;
	}
}