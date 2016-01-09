# Piwik Visit Export and Import

Sometimes during Piwik plugin development it is helpful to have some production data available in your local database.
This plugin allows to export some visit data from a Piwik instance and import it into another one.


## How to export and import visits

After having installed this plugin, you can run `./console visit-export --startDate=YYYY-MM-DD --endDate=YYYY-MM-DD`
from the main directory of your Piwik installation. This exports all visits from your database for the given time 
(0 o´clock of the start date until 23:59:59 o´lock of the end date) into `visit-export.json`.

You can import `visit-export.json` by executing `./console visit-import`.


## Limitations

__Existing visits, actions etc. will be overwritten with imported data!__

Imported data will not be considered in various computed stats and reports. It will only be available in the database
as raw data in tables such as piwik_log_visit, piwik_log_action, piwik_log_link_visit_action and piwik_log_conversion.


## Requirements and installation

To install this plugin, follow Piwik's "[How to install a plugin](http://piwik.org/faq/plugins/#faq_21)" guide.

The website/s the visitors belong to must exist in both your export and import database.

Make sure the columns of the database tables in your export and import database match.


## License

GPL v3 / fair use


## TODO

* Consider piwik_goal and piwik_log_conversion_item
* Consider archives? Is there are chance to re-compute them?
* The JSON file might become huge
* Exporting and importing becomes slow when there is lots of data
* PHP fatal error "Allowed memory size exhausted..." might occur
