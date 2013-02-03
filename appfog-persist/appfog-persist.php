<?php
/*
Plugin Name: wp-appfog-persist
Plugin URI: https://github.com/okeez/wp-appfog-persist
Description: AppFog WordPress Uploads Persistence Tool
Author: Bob de Wit
Version: 1.0
Author URI: http://okeez.com
License: GPLv2 or later

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

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    
*/

define( 'APPFOG_PERSISTS_PATH', plugin_dir_path(__FILE__) );

$ap = new AppFogPersist();
$ap->initialize();
$ap->persist();

add_action ( 'edit_attachment', 'persistAttachment', 1);
add_action ( 'edit_attachment', 'persistOnLoad', 1);

function persistAttachment()
{
    $ap = new AppFogPersist();
    $ap->persist(true);
}

function persistOnLoad()
{
    $ap = new AppFogPersist();
    $ap->persist(false);
}

class AppFogPersist
{
    
    public function initialize()
    {
        $this->createTable();
    }
    
    public function persist($force = false)
    {
        global $wpdb;
        
        //Check if a persistence cycle is already running by trying to 
        //open an exclusive file lock. If the lock can't be established,
        //there's no need to run the persistence cycle right now.
        $fp = fopen(plugin_dir_path(__FILE__) . 'persist.lck', 'r+');
        if (flock($fp, LOCK_EX | LOCK_NB) || $force) 
        {
            //Get the path to the upload folder 
            $upload_dir = wp_upload_dir();
            //echo "Upload dir: " . $upload_dir['basedir'] . "\n";
            
            //Get all subfolders and files in the upload folder 
            $ite = new RecursiveDirectoryIterator($upload_dir['basedir']);
            
            //Loop through all files and persist any new ones
            foreach (new RecursiveIteratorIterator($ite) as $filename=>$cur) 
            {
                //Do not persist folders
                if(!is_dir($filename))
                {
                    //Do not persists symbolic links
                    if(!is_link($filename))
                    {                        
                        //Get the native path for the file + uploads subfolder                       
                        $safename = addslashes(str_replace($upload_dir['basedir'], '', $filename)); 
                        echo "$safename\n";
                        
                        //Check if this file is already persisted in the database
                        $sql = "SELECT id FROM wp_appfog_persist WHERE path = '$safename'";
                        $rec = $wpdb->get_row($sql);
                        
                        //If there is no database record for this file, create it
                        if($rec == NULL)
                        {
                            $binary = addslashes(file_get_contents($filename));
                            $sql = "INSERT INTO wp_appfog_persist (id, path, data) VALUES (NULL, '$safename', '{$binary}')";
                            $wpdb->query($sql);
                        }
                    } 
                }
            } 
            
            //Now check if all persisted files in the database still exist            
            $sql = "SELECT id, path from wp_appfog_persist";            
            $pfiles = $wpdb->get_results($sql);
            
            //Loop through all persisted files and check
            foreach($pfiles as $pfile)
            {
                //If the file is no longer there, restore it from the db
                $fullPath = $upload_dir['basedir'] . $pfile->path;
                
                if(!file_exists($fullPath))
                {
                    //First, create the folder path if necessary
                    $folder = dirname($fullPath);
                    $id = $pfile->id;
                    if(!is_dir($folder))
                    {
                        mkdir($folder, 0644, true);
                    }
                    
                    //Now get the binary data and write it to the file path
                    $sql = "SELECT * from wp_appfog_persist WHERE id = $id";
                    $rec = $wpdb->get_row($sql);
                    file_put_contents($fullPath, $rec->data);
                }
            } 
        }
        
        //Finally, close the exclusive file lock        
        fclose($fp);    
    }
    
    public function createTable()
    {
        global $wpdb;
        $sql = "CREATE TABLE IF NOT EXISTS wp_appfog_persist 
        (
            id int(11) NOT NULL AUTO_INCREMENT,
            path varchar(512) NOT NULL,
            data blob,
            PRIMARY KEY (id),
            KEY path (path)
        );"; 
        
        $wpdb->query($sql);        
    }            
}