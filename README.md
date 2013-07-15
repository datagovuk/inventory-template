# inventory-template

This script uses PHPExcel to generate a spreadsheet that can then be completed by publishers before uploading into the ckanext-dgu inventory tool. 

By providing the name of a publisher, a specific column will be populated with cells that performvalidation on all of the sub-publishers.

## Installation

You may require the PHP postgres driver, you can install this on ubuntu with:

    sudo apt-get install php5-pgsql

Your apache config should (in the appropriate site config file) contain something like:

    Alias /inventory/template /var/www/inventory-template
    <Directory /var/www/inventory-template>
        FileETag MTime Size
        Options FollowSymLinks MultiViews
        AllowOverride All
        Order allow,deny
        allow from all
        SetEnv CKAN_CONFIG_FILE /path_to_ckan.ini
    </Directory>

Installation requires that the apache configuration use SetEnv to specify the CKAN_CONFIG_FILE environment variable to point to a CKAN .ini file which contains the database settings.
