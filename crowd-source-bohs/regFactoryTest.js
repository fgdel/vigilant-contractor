var RegFactorySolFile = artifacts.require("./RegFactory.sol");
var Registration = artifacts.require("Registration");

//regFactory global variable
let lastDeployedContractAddress = [];

//list of accounts - Truffle 
contract('RegFactory', function (accounts) {

  //run test
  let numberOfTests = 3;

  //this function creates 10 regFactories
  
  function loop10Creations(i) {
    if(`Contract item is correctly stored for #${i} contract. Item is ${accounts[i]}`, function () {
      return RegFactorySolFile.deployed().then(function (instance) {
        regFactoryInstance = instance;
        return regFactoryInstance.createContract(`Contract ${i}`, { from: accounts[i] });
      }).then(function () {
        return regFactoryInstance.getOriginalItem.call(0);
      }).then(function (item) {
        assert.equal(item, accounts[0], "The contract wasn't created");
        return regFactoryInstance.getNameArray.call();
      }).then(function (nameArray) {
        if (i == numberOfTests) console.log(nameArray)
        return regFactoryInstance.getContractAddressArray.call();
      }).then(function (addressArray) {
        if (i == numberOfTests) {
          console.log(addressArray);
          lastDeployedContractAddress = addressArray[i]; //always find last contract
        }
        return regFactoryInstance.getOriginalItemArray.call();
      }).then(function (itemArray) {
        if (i == numberOfTests) console.log(itemArray);
      });
    });
  }
  for (let i = 0; i <= numberOfTests; i++) {
    loop10Creations(i);
  }
});

