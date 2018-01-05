Smart Contract Example - Crowd source sponsroship DApp - https://github.com/fgdel/vigilant-contractor/blob/contract_Sponsor_0/contract-Sponsor

Some challenging security features proposed here, running counter to a strict decentralised ethos, in particular the inclusion of facilities to amend and audit the ledger by the contract owner. 


/*********************************************Modifiers, Events, enums******************************************************/

    modifier onlyCreator {
        if (msg.sender != creator) 
            revert();
        _; 
    }

    modifier onlySponsors {
        if (msg.sender == creator) 
            revert();
        _;
    }
    
    //owner is the Club who put the Team Factory on the blockchain 
    //They have the ability to Fix contract ledger if there are mistakes
    modifier onlyOwner {
        if (msg.sender != owner) 
            revert();
        _;
    }
    
    modifier notInMaintenance {
        healthCheck();
        if (maintenance_mode >= maintenance_Emergency) 
            revert();
        _;
    }
        
    event LOG_SingleRegister (uint registerAmount, address registrar);
    event LOG_SponsorContractCreated (address creator, address createdContract);
    event LOG_ChangeToSingleSponsorStruct (uint totalRegisterStart, uint totalRemaining, address registrar);
    event LOG_ChangeToFullLedger (uint allSponsorsEver, uint SponsorsNow, uint totalRegistersEver, 
	uint totalRegistersWithdrawn, uint totalRegistersCancelled, uint totalEtherEver, uint totalEtherNow, 
	uint totalEtherWithdrawn);
    event LOG_ChangeToContractBalance (uint contractBalance);
    event LOG_HealthCheck(bytes32 message, int diff, uint balance, uint ledgerBalance);


/*********************************************HEALTH CHECK FUNCTIONS***********************************************/
	

    function healthCheck() internal {
        // minus msg.value because the contract balance increases at start from payable functions, and ledger 
	only decreases at end of payable function

    	int diff = int(this.balance-msg.value) - int(ledger[totalEtherNow]);//needs to be int for negative
		if (diff == 0) {
			return; // everything is ok.
		}
		if (diff > 0) {
			LOG_HealthCheck("Balance too high", diff, this.balance, ledger[totalEtherNow]);
			maintenance_mode = maintenance_BalTooHigh;
		} else {
			LOG_HealthCheck("Balance too low", diff, this.balance, ledger[totalEtherNow]);
			maintenance_mode = maintenance_Emergency;
		}
	}

	// manually perform healthcheck.
	// 	 0 = reset maintenance_mode, even in emergency
	// 	 1 = perform health check
	//         255 = set maintenance_mode to maintenance_emergency (no newPolicy
		function performHealthCheck(uint8 _maintenance_mode) external onlyOwner {
		maintenance_mode = _maintenance_mode;
		if (maintenance_mode > 0 && maintenance_mode < maintenance_Emergency) {
			healthCheck();
		}
	}
    
    // if the ledger is corrupted, can be corrected by the owner
	function auditLedger(uint8 _from, uint8 _to, uint _amount) external onlyOwner {

		ledger[_from] -= uint(_amount);
		ledger[_to] += uint(_amount);
        	LOG_ChangeToFullLedger (ledger[allSponsorsEver], ledger[SponsorsNow], ledger[totalRegistersEver], 			ledger[totalRegistersWithdrawn], ledger[totalRegistersCancelled], ledger[totalEtherEver], 				ledger[totalEtherNow], ledger[totalEtherWithdrawn]);
	}
    //if necessary amend specific registrars struct
    
function auditSponsor(uint _totalRemaining, uint _SponsorID) external onlyOwner {

        registrars[_SponsorID].totalRemaining = _totalRemaining;
        LOG_ChangeToSingleSponsorStruct(registrars[_SponsorID].totalRegisterStart,  
	registrars[_SponsorID].totalRemaining, registrars[_SponsorID].registrar);

    }
	
	//owner can return money to a Sponsor
	//useful for testing test
	function refundForMistake(address refundee, uint amount) external onlyOwner {

        refundee.transfer(amount);
		maintenance_mode = maintenance_Emergency; // don't allow any contributions or withdrawals
        LOG_ChangeToContractBalance(this.balance);

	}


