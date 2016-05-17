# slot-machine-spins
A bare-bones Web service written in php

This example uses a mySql database with 1 table and two php files. This was truly an immersion into a brand new language (PHP - I'd never looked at it until 4:00 pm Monday, May 16th) as well as a full windows envrionment setup for the stack.

### Prep
A full WAMP stack was created to set up the proper environment. From scratch, I pulled in Apache 2.4, PHP 7.0.6 and MySQL 5.7.12.
Properly configuring everything ended at about 4:00pm. From there, I downloaded PHPstorm and began to learn PHP.

### Coding
* First off was running a few test pages to determine the basic workflow. about an hour
* Next up was a simple round-trip REST interaction with json being returned. Another 1.5 hours
* Build the database. opted for a single table with fields to be able to support all the bullet items in the requirements. about an hour to set it up.
* generate the spin data from a client. This involved starting with known data and then adding a random component during final testing. To play with the OOP side of PHP, I created a spin-entry object so I could examine how to manipulate it.
 
### Web Service
* build the web service. I broke the system up into capture, basic validation, database entry after specific validation.
  * basic validation: added some regex and presence testing to make sure the spindata was all there and in good order. I opted to merge all the data into one parameter with the intention of adding some obfuscation all at once as a future add-on. Something like a rot13 or other system. The data is broken up into an array of key/value pairs - nice feature in PHP.
  * database entry after specific validation: because the user's row needed to be used to finalize other validations - like password validity and remaining credit, a few validations were performed just prior to the database update.
    * because the return data had derived values, the database portion also performs some of the maths required and stores them in the return object.
    * Validity failures are an array of error messages. In the future, form validation across multiple inputs could be established so that all the offending fields show up red at once rather than one at a time.
  * response generation: I generate the required reply through prepareResponse(). It views the collected data and generates a subset of the program actions and returns it to the client as a json object.
   
  
### Bare Bones Client
  * slot-machine.php generates a random entry for database id 1 and POSTS it to the server. It displays the parameters it sends and the responses it receives.
  * multiple refreshes of the client roll random results for the betting and the winning, so you will see the database reflecting the wins and losses.
  * Since this was a supplement to the assignment for testing, it is only a generator. The next steps would be to add a login page, user creation and form fields to play with differing reults.
  
 
  


