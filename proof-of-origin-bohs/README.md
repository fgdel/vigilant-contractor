# proofoforigin on blockchain

Extension to the existing MultiChain PHP Library [libphp-multichain] (https://github.com/Kunstmaan/libphp-multichain) by adding this below method to the poe/poe-api/vendor/kunstmaan/libphp-multichain/src/be/kunstmaan/multichain/MultichainClient.php file.

	public function executeApi($method, $paramArray) {
        	return $this->jsonRPCClient->execute($method, $paramArray);
	}

## License 
Source code available under [Apache License 2.0 (Apache-2.0)] (https://tldrlegal.com/license/apache-license-2.0-(apache-2.0))  
    
