<?php

if (!defined('ABSPATH'))
  exit;

use Dompdf\Dompdf as Dompdf;

if (!class_exists('cf7pdf_panel')) {
    class cf7pdf_panel {

        protected static $instance;        

        function cf7pdf_editor_panels( $panels ) { 
            $paypal = array(
                'pdf-panel' => array(
                    'title' => __( 'PDF Setting', 'contact-form-7' ),
                    'callback' => array( $this, 'cf7pdf_editor_panel_popup'),
                ),
            );
            $panels = array_merge($panels,$paypal);
            return $panels; 
        }


        function cf7pdf_editor_panel_popup() { 
            $formid = sanitize_text_field($_REQUEST['post']);
            // POPUP ADMINPANEL FORMAT
            ?>
            <h2>PDF Settings</h2>
            <fieldset>
                <table class="pdf_main">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label>DownLoad Pdf</label>
                            </th>
                            
                            <td>
                                <?php $enabled_pdf  = get_post_meta( $formid, CF7PDF_PREFIX.'enabled_pdf', true ); ?>
                                <input type="checkbox" name="enabled_pdf" <?php if($enabled_pdf == "on"){ echo "checked"; }?>><label>DownLoad Pdf</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label>Send Pdf in mail</label>
                            </th>
                            
                            <td>
                                <?php $send_pdf  = get_post_meta( $formid, CF7PDF_PREFIX.'send_pdf', true ); ?>
                                <input type="checkbox" name="send_pdf" <?php if($send_pdf == "on"){ echo "checked"; }?>><label>Send Pdf to mail</label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            
            <?php 
        }

        
        function cf7pdf_after_save( $instance) { 
    
            $formid = $instance->id;

            $enabled_pdf = sanitize_text_field($_POST['enabled_pdf']);
            update_post_meta( $formid, CF7PDF_PREFIX.'enabled_pdf', $enabled_pdf );

            $send_pdf = sanitize_text_field($_POST['send_pdf']);
            update_post_meta( $formid, CF7PDF_PREFIX.'send_pdf', $send_pdf );
        }


        function cf7pdf_save_form( $wpcf7 ) {
            
            global $wpdb;
            $table_name    = $wpdb->prefix.CF7PDF_TABLE;
            $upload_dir    = wp_upload_dir();
            $cf7pdf_dirname = $upload_dir['basedir'].'/'.CF7PDF_UPLOAD;
            $time_now      = time();


            $form = WPCF7_Submission::get_instance();
            if ( $form ) {

                $data  = $form->get_posted_data();
                $files = $form->uploaded_files();

                $black_list   = array('_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag',
                '_wpcf7_is_ajax_call', '_wpcf7_container_post','_wpcf7cf_hidden_group_fields',
                '_wpcf7cf_hidden_groups', '_wpcf7cf_visible_groups', '_wpcf7cf_options','g-recaptcha-response');


                $uploaded_files = array();
                foreach ($files as $file_key => $file) {
                    array_push($uploaded_files, $file_key);
                    copy($file, $cf7pdf_dirname.'/'.$time_now.'-'.basename($file));         
                }

                $form_data   = array();
                $form_data['cf7pdf_status'] = 'unread';
                foreach ($data as $key => $d) {
                   
                    $matches = array();

                    if ( !in_array($key, $black_list ) && !in_array($key, $uploaded_files ) && empty( $matches[0] ) ) {

                        $tmpD = $d;

                        if ( ! is_array($d) ){

                            $bl   = array('\"',"\'",'/','\\','"',"'");
                            $wl   = array('&quot;','&#039;','&#047;', '&#092;','&quot;','&#039;');

                            $tmpD = str_replace($bl, $wl, $tmpD );
                        }

                        $form_data[$key] = $tmpD;
                    }
                    if ( in_array($key, $uploaded_files ) ) {
                        $form_data[$key] = $time_now.'-'.$d;
                    }
                }

                

                $form_post_id = $wpcf7->id();
                $form_value   = serialize( $form_data );
                $form_date    = current_time('Y-m-d H:i:s');
                $wpdb->insert( $table_name, array(
                    'form_post_id' => $form_post_id,
                    'form_value'   => $form_value,
                    'form_date'    => $form_date
                ) );

                $insert_id = $wpdb->insert_id;
                $_SESSION['insert_id'] = $insert_id; 

            }
        }


        function cf7pdf_ajax_json_echo( $response, $result ) {
            
            $formid                     = $result['contact_form_id'];
            $enabled_pdf                = get_post_meta( $formid, CF7PDF_PREFIX.'enabled_pdf', true );
            
            $response[ 'enabled_pdf' ]  = $enabled_pdf;
            $response[ 'insert_id' ]    = $_SESSION['insert_id'];
            $response[ 'pdf_button' ]   = '<a href="?action=pdf_callback&form_id='.$formid.'&pdf_id='.$_SESSION['insert_id'].'" class="cf7pdf_button" >DownLoad PDF</a>';
            return $response;
        }


        function cf7pdf_footer() {
            ?>
            <script>
                document.addEventListener( 'wpcf7mailsent', function( event ) {
                    if(event.detail.apiResponse.enabled_pdf == "on"){
                        jQuery('.wpcf7-form').append(event.detail.apiResponse.pdf_button);
                    }
                }, false );

            </script>
            <?php
        }


        function cf7pdf_download_pdf(){
            if(isset($_REQUEST['action']) && $_REQUEST['action'] == "pdf_callback"){
                $form_id   = sanitize_text_field($_REQUEST['form_id']);
                $insert_id = sanitize_text_field($_REQUEST['pdf_id']);
                include_once(plugin_dir_path( __FILE__ ).'dompdf/autoload.inc.php');
                ob_start();

                global $wpdb;
                $table_name    = $wpdb->prefix.CF7PDF_TABLE;
                $upload_dir    = wp_upload_dir();
                $cf7pdf_dir_url = $upload_dir['baseurl'].'/'.CF7PDF_UPLOAD;

                $results    = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_post_id = $form_id AND form_id = $insert_id LIMIT 1", OBJECT );
                
                ?>
                    <div class="cf7pdf_template" >
                        <html>
                            <head>
                                <title>Contect form 7</title>
                            </head>
                            <body>
                                <main class="page">
                                    <table style="border-collapse:collapse; width: 100%;">
                                        <tr>
                                            <td>
                                                <h2><?php echo get_the_title( $form_id ); ?></h2> 
                                            </td>
                                            <td style="width: 40%;">
                                                <span><?php echo $results[0]->form_date; ?></span>
                                            </td>  
                                        </tr>
                                    </table>
                                    <div class="table-add" style=" width:100%;margin: 0px;padding: 0px;">
                                        <table>
                                            <?php 
                                                $form_data  = unserialize( $results[0]->form_value );

                                                foreach ($form_data as $key => $data):
                                                   if ( $key == 'cf7pdf_status' )  continue;
                                                   echo '<tr style="padding: 10px;line-height: 50px;">';
                                                      if ( $key == 'cf7wpay_status' )  continue;
                                                      $key_val       = str_replace( array('your-'), '', $key);
                                                      
                                                      echo '<td style="width: 100px;">'.ucfirst( $key_val ).'</td>';


                                                      $supported_image = array('gif', 'jpg', 'jpeg', 'png', 'pdf');

                                                      $ext = strtolower(pathinfo($data, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
                                                      if (in_array($ext, $supported_image)) {
                                                         echo '<td><img src="'.$cf7pdf_dir_url.'/'.$data.'" target="_blank"></a></td>'; 
                                                      } else {
                                                         echo '<td>'.$data.'</td>';
                                                      }
                                                   echo "</tr>";
                                                endforeach;      
                                            ?>
                                        </table>
                                        
                                    </div>
                                </main>
                            </body> 
                        </html>
                    </div>
                <?php
                $html=ob_get_clean();
                
                $dompdf = new Dompdf();
               
                $dompdf->loadHtml($html);
          
                $dompdf->setPaper('A4', 'portrait');
                
                $dompdf->render();
            
                $dompdf->stream( $insert_id.'.pdf', array("Attachment" => true));
                
            }
        }


        function send_mail_attchment($components, $form, $object) {
            
              
            $form_id      = $form->id;
            $insert_id    = $_SESSION['insert_id'];
            $send_pdf  = get_post_meta( $form_id, CF7PDF_PREFIX.'send_pdf', true );
            if($send_pdf == "on"){
                include_once(plugin_dir_path( __FILE__ ).'dompdf/autoload.inc.php');
                ob_start();

                global $wpdb;
                $table_name    = $wpdb->prefix.CF7PDF_TABLE;
                $upload_dir    = wp_upload_dir();
                $cf7pdf_dir_url = $upload_dir['baseurl'].'/'.CF7PDF_UPLOAD;

                $results    = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_post_id = $form_id AND form_id = $insert_id LIMIT 1", OBJECT );
                
                ?>
                    <div class="cf7pdf_template" >
                        <html>
                            <head>
                                <title>Contect form 7</title>
                            </head>
                            <body>
                                <main>
                                    <table style="border-collapse:collapse; width: 100%;">
                                        <tr>
                                            <td>
                                                <h2><?php echo get_the_title( $form_id ); ?></h2> 
                                            </td>
                                            <td style="width: 40%;">
                                                <span><?php echo $results[0]->form_date; ?></span>
                                            </td>  
                                        </tr>
                                    </table>
                                    <div class="table-add" style=" width:100%;margin: 0px;padding: 0px;">
                                        <table>
                                            <?php 
                                                $form_data  = unserialize( $results[0]->form_value );

                                                foreach ($form_data as $key => $data):
                                                   if ( $key == 'cf7pdf_status' )  continue;
                                                   echo '<tr style="padding: 10px;line-height: 50px;">';
                                                      if ( $key == 'cf7wpay_status' )  continue;
                                                      $key_val       = str_replace( array('your-'), '', $key);
                                                      
                                                      echo '<td style="width: 100px;">'.ucfirst( $key_val ).'</td>';


                                                      $supported_image = array('gif', 'jpg', 'jpeg', 'png', 'pdf');

                                                      $ext = strtolower(pathinfo($data, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
                                                      if (in_array($ext, $supported_image)) {
                                                         echo '<td><img src="'.$cf7pdf_dir_url.'/'.$data.'" target="_blank"></a></td>'; 
                                                      } else {
                                                         echo '<td>'.$data.'</td>';
                                                      }
                                                   echo "</tr>";
                                                endforeach;      
                                            ?>
                                        </table>
                                    </div>
                                </main>
                            </body> 
                        </html>
                    </div>
                <?php
                $html=ob_get_clean();
                
                $dompdf = new Dompdf();
               
                $dompdf->loadHtml($html);
          
                $dompdf->setPaper('A4', 'portrait');
                
                $dompdf->render();
            
                $output = $dompdf->output();
                $wordpress_upload_dir = wp_upload_dir();
                file_put_contents( $wordpress_upload_dir['[basedir]'].$insert_id.'.pdf', $output);
                    $file = $wordpress_upload_dir['[basedir]'].$insert_id.'.pdf';
                    $filename = basename($file);
                    $upload_file = wp_upload_bits($filename, null,  file_get_contents($wordpress_upload_dir['[basedir]'].$insert_id.'.pdf') 
                 );
                   
                if(!$upload_file['error']) {
                  $wp_filetype = wp_check_filetype($filename, null );
                  $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                    'post_content' => '',
                    'post_status' => 'inherit'
                  );
                  $attachment_id = wp_insert_attachment( $attachment, $upload_file['file'], $parent_post_id );
                  if (!is_wp_error($attachment_id)) {
                    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                    $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_file['file'] );
                    wp_update_attachment_metadata( $attachment_id,  $attachment_data );
                  }
                    unlink($file);
                }
               $your_pdf_path =  wp_get_attachment_url( $attachment_id );
        
                $components['attachments'] = array($your_pdf_path);
                }
              return $components;
            }


        function init() {   
            add_filter( 'wpcf7_editor_panels', array( $this, 'cf7pdf_editor_panels'), 10, 1 ); 
            add_action( 'wpcf7_after_save', array( $this, 'cf7pdf_after_save'), 10, 1 ); 
            add_action( 'wpcf7_before_send_mail', array( $this, 'cf7pdf_save_form'));
            add_filter( 'wpcf7_ajax_json_echo', array( $this, 'cf7pdf_ajax_json_echo'), 20, 2 );
            add_action( 'wp_footer', array($this, 'cf7pdf_footer'));
            add_action( 'init', array($this, 'cf7pdf_download_pdf'));
            add_filter( 'wpcf7_mail_components', array($this, 'send_mail_attchment'), 10, 3);
        }


        public static function instance() {
            if (!isset(self::$instance)) {
                self::$instance = new self();
                self::$instance->init();
            }
            return self::$instance;
        }
    }
    cf7pdf_panel::instance();
}













