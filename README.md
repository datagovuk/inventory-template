# inventory-template

This script uses PHPExcel to generate a spreadsheet that can then be completed by publishers before uploading into the ckanext-dgu inventory tool. 

By providing the name of a publisher, a specific column will be populated with cells that performvalidation on all of the sub-publishers.

## Installation

Installation requires that the apache configuration use SetEnv to specify the CKAN_CONFIG_FILE environment variable to point to a CKAN .ini file which contains the database settings.
