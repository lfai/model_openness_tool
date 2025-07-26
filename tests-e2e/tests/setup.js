const { execSync } = require('child_process');

async function globalSetup() {
  console.log('Setting up admin password for testing...');
  // Setting admin password to a known value for testing
  try {
    const output = execSync('../vendor/bin/drush user:password admin "adminpw"');
  } catch (error) {
    console.error('Error setting password:', error);
  }  
  
  console.log('Admin password set successfully');
}
module.exports = globalSetup;
