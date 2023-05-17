# Matrix's fork of Quickstatements 2.0 for the Enslaved project

### Install instructions
```
Clone
Create database using the new_scheme.sql file
Create and edit the /public_html/config.json file based off of /public_html/config.json.template
Propose new consumer in your wikibase install
Add the token and secret to the /public_html/.oauth.ini file based off of /public_html/.oauth.ini.template
Accept your new consumer in your wikibase install
```

### Use instructions
Sequential Bot: \
This is the classic and most stable quickstatement bot. The stability comes with the drawback of being quite slow for large batches.

Fast Batch Bot: \
This action is set by default to run 40 bots at the same time for increased speed. This way is quite fast but statements won't be run in the order given in the input file. Only use this if statement order doesn't matter.

Edit Only Bot: \
This is primarily for large amounts of statements/changes to singular items. This is the fastest option for this situation but there is limit to the total number of changes that will work at one time.

### Major changes explained
* The Fast Bot uses the new /bot_fast.php and /botChild.php files to spawn extra php processes to run multiple bots at the same time. \
The number of bots seems to work well for us at 40. \
This number can be easily changed at /bot_fast.php line 49

* A new commands database table is created for each batch in order to reduce slow down from large table size after a lot of use. \
/quickstatments.php line 175

* The edit only bot creates api requests a bit differently by builing all statements affecting singular items into one api call. \
This can be seen at /public_html/quickstatements.php line 1880

* Statement files are now immediately proccessed and put into the database. This used to require a specification to run in background. This cuts file processing time roughly in half and provides a better user experience.

* A copy of magnustools has been included in the repo to simplify install and provide the full product in one repo.

