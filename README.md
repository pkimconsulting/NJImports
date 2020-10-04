# NJImports

### Overview
This is a set of 2 Drupal 8 modules (only tested on Drupal 8.8/8.9).

**nj_import** - This module is separated into four different parts:

1. Fetcher - a fetcher is an interface that acts as a glue between the API call and the data required. Currently, only HTTP Requests have been implemented using the GuzzleHTTP client (http://docs.guzzlephp.org/en/stable/). Future implementations might include: Raw files, AWS SQS, FTP/SFTP/SSH, Database interface, etc.

2. Parser - A parser is the format the data is in. Currently only JSON is supported through JSONPath (https://github.com/FlowCommunications/JSONPath). Future implementations might include: CSV, XMLRPC, RSS, HTML scraping, etc.

3. Processor - This connects the raw results from the Parser to a Drupal entity. We only support nodes at this point.

4. Target - These are the different fields for a fieldable entity in Drupal.  Support for text, integer, number, Date fields.

**nj_import_mockapi** - This module has custom business logic to:

- Check if the item in the database has enough quality
- Check that the availability date is <= order date
-  Reduces the quantity of the item's quantities if the entire order is successful

### Configs
You'll see the node and field definitions in the config directory. This is using Drupal's standard config management module.

### Code
Most of the code is written. I've tested out some scenarios, but this is still a work in progress. A lot of the interfaces are stubbed out, but not everything is being used.

### Demo
There is a demo environment setup at: https://dev-ordering-system.pantheonsite.io/

You can see some of the orders and the items imported from the homepage. For example, Order 69 failed: https://dev-ordering-system.pantheonsite.io/node/156 because order 68 and order 12 made the quanity of item 25 go down to 0. Afterwards, for testing, I have reset the quantity with a fresh import.

You can see some items, such as https://dev-ordering-system.pantheonsite.io/node/25
