wp-appfog-persist
=================

This simple plugin addresses the issue with persisting uploads in WordPress
running on an AppFog Instance, since AppFog currently lacks persisting files 
that were added at runtime. This means that files uploaded to the instance
will dissapear after a restart of the instance. 

This plugin simply scans the uploads folder and will persist all newly 
uploaded files therein to the WordPress MySQL database. It then compares 
the persisted files and folders with the ones in the database and re-creates
any missing files and subfolders in the uploads folder. 

Warning: you MUST install this plugin in a local copy of your WordPress
instance and "af update" to AppFog. Installing this plugin in the running
AppFog instance will cause the plugin to dissapear after restart just
like your other runtime modifications and file uploads.