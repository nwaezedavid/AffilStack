const { version: platformVersion } = require('zapier-platform-core');

const authentication = require('./authentication');
const includeApiKey = require('./middleware/includeApiKey');
const newOfferResearchCompleted = require('./triggers/newOfferResearchCompleted');
const newCrmContact = require('./triggers/newCrmContact');
const createOffer = require('./creates/createOffer');
const createCrmContact = require('./creates/createCrmContact');

const { version } = require('./package.json');

module.exports = {
  version,
  platformVersion,

  authentication,

  beforeRequest: [includeApiKey],

  resources: {},

  triggers: {
    [newOfferResearchCompleted.key]: newOfferResearchCompleted,
    [newCrmContact.key]: newCrmContact,
  },

  creates: {
    [createOffer.key]: createOffer,
    [createCrmContact.key]: createCrmContact,
  },

  searches: {},
};
