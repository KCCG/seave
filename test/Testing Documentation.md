# Software requirements
1. Xvfb
  * Current version in apt-get
2. Firefox
  * Must be version 46 for now, newest (47) does not communicate with Selenium properly as the driver is in transition to a new standard (geckodriver), unfortunately the new standard is also broken. Luckily v46 works so it needs to be installed manually with `sudo ln -s /path/to/firefox /usr/bin/firefox`
3. Selenium Server (jar)
  * v2.53 included in the repository
4. Perl + modules
  * Selenium::Remote::Driver
  * Mozilla::CA
  * LWP::Simple

# Headless testing process/environment
1. Run Xvfb
2. Set DISPLAY environment variable to the display of Xvfb
3. Run the Selenium Server jar
4. Run Firefox
5. Run the testing Perl script
6. Kill the 3 processes started above

# Builder process
1. Check out branch of interest and tar it
2. Save (tar) and delete current deployment on dev.seave.bio
3. Copy checked out code to dev.seave.bio to the website directory, untar and set the permissions as normal for the webserver
4. Start Xvfb, Selenium and Firefox and save their process IDs
5. Run the testing Perl script to obtain the output XML
6. Kill the processes
7. Copy the XML file to the builder and run it through the JUnit parser
8. Delete the new code from dev.seave.bio and restore the old deployment from the tar created initially

# Local testing
The Selenium.pl script can be run locally on any computer which is:
1. Running Selenium server (jar file)
2. Has Firefox installed
3. Has Perl + required modules installed
To run, simply run `perl /path/to/Selenium.pl /path/to/output.xml`. This will open Firefox on your screen and perform the testing and write the output file.