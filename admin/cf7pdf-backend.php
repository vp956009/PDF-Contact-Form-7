<?php

if (!defined('ABSPATH'))
  exit;

if (!class_exists('cf7pdf_form_list')) {
   class cf7pdf_form_list {

      protected static $instance;        

      function cf7pdf_menu_pages(){
         add_submenu_page( 'wpcf7', __( 'PDF Entries', 'cf7wpay' ), __( 'PDF Entries', 'cf7wpay' ),'manage_options', CF7PDF_PAGE_SLUG, array($this, 'cf7pdf_list_table_page') );
      }
        

      function cf7pdf_list_table_page(){

         $cf7pdf_formid  = empty($_GET['cf7pdf_formid']) ? 0 : (int) $_GET['cf7pdf_formid'];
         $cf7pdf_entryid = empty($_GET['cf7pdf_entryid']) ? 0 : (int) $_GET['cf7pdf_entryid'];

         if ( !empty($cf7pdf_formid) && empty($_GET['cf7pdf_entryid']) ) {

            new cf7pdf_sub_list_table();
            return;

         }else if( !empty($cf7pdf_formid)  &&  !empty($cf7pdf_entryid)){

            new cf7pdf_entry_details();
            return;

         }else{

            $ListTable = new cf7pdf_main_list_table();
            $ListTable->prepare_items();

         }
         ?>
            <div class="wrap">
               <div id="icon-users" class="icon32"></div>
               <h2><?php _e( 'Contact Forms Data List', 'cf7wpay' ); ?></h2>
               <?php $ListTable->display(); ?>
            </div>
         <?php
      }
        

      function init() {   
         add_action( 'admin_menu',array($this, 'cf7pdf_menu_pages'));
      }


      public static function instance() {
         if (!isset(self::$instance)) {
            self::$instance = new self();
            self::$instance->init();
         }
         return self::$instance;
      }
   }
   cf7pdf_form_list::instance();
}



if( ! class_exists( 'WP_List_Table' ) ) {
   require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


/*============= main cf7 list =============*/
class cf7pdf_main_list_table extends WP_List_Table {

   public function prepare_items() {
      $columns     = $this->get_columns();
      $hidden      = $this->get_hidden_columns();
      $data        = $this->table_data();
      $perPage     = 10;
      $currentPage = $this->get_pagenum();
      $totalItems  = count($data);
      $this->set_pagination_args( array(
         'total_items' => $totalItems,
         'per_page'    => $perPage
      ) );
      $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
      $this->_column_headers = array($columns, $hidden );
      $this->items = $data;		
   }
    

   public function get_columns() {
      $columns = array(
         'name' => __( 'Name', 'cf7wpay' ),
         'count'=> __( 'Count', 'cf7wpay' )
      );
      return $columns;
   }


   public function get_hidden_columns(){
      return array();
   }


   private function table_data(){
      global $wpdb;

      $data         = array();
      $table_name   = $wpdb->prefix.CF7PDF_TABLE;
      $args = array(
         'post_type'=> 'wpcf7_contact_form',
         'order'    => 'ASC',    
      );

      $the_query = new WP_Query( $args );
      while ( $the_query->have_posts() ) : $the_query->the_post();
         $form_post_id = get_the_id();
         $totalItems   = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE form_post_id = $form_post_id");
         $title = get_the_title();
         $link  = "<a class='row-title' href=admin.php?page=".CF7PDF_PAGE_SLUG."&cf7pdf_formid=$form_post_id>%s</a>";
         $data_value['name']  = sprintf( $link, $title );
         $data_value['count'] = $totalItems;
         $data[] = $data_value;
      endwhile;
      return $data;
   }


   public function column_default( $item, $column_name ){
      return $item[ $column_name ];
   }
}
/*=========== end main cf7 list ===========*/



/*=========== sub cf7 list ================*/
class cf7pdf_sub_list_table {
   public function __construct() {
      $this->form_post_id = (int) sanitize_text_field($_GET['cf7pdf_formid']);
      $this->subform_table_page();
   }
   
   public function subform_table_page() {
      $ListTable = new sub_data_list_table();
      $ListTable->data_prepare_items();
      ?>
         <div class="wrap">
            <div id="icon-users" class="icon32"></div>
            <h2><?php echo get_the_title( $this->form_post_id ); ?></h2>
            <form method="post" action="">
               <input type="hidden" name="cf7pdf_bulk" value="forcsv">
               <?php $ListTable->display(); ?>
            </form>
         </div>
      <?php
   }
}


class sub_data_list_table extends WP_List_Table{
   private $form_post_id;
   private $column_titles;

   public function __construct() {
      parent::__construct(
         array(
            'singular' => 'contact_form',
            'plural'   => 'contact_forms',
            'ajax'     => false
         )
      );
   }


   public function data_prepare_items() {
      $this->form_post_id =  (int) sanitize_text_field($_GET['cf7pdf_formid']);
      $form_post_id   = $this->form_post_id;
      $columns        = $this->get_columns();
      $hidden         = $this->get_hidden_columns();
      $sortable       = $this->get_sortable_columns();
      $data           = $this->table_data();
      $perPage        = 10;
      $currentPage    = $this->get_pagenum();
      $totalItems     = count($data);
      $this->set_pagination_args( array(
         'total_items' => $totalItems,
         'per_page'    => $perPage
      ) );
      $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
      $this->_column_headers = array($columns, $hidden ,$sortable);
      $this->items = $data;
      $this->process_bulk_action();
   }


   public function get_columns() {
      global $wpdb;
      $form_post_id = $this->form_post_id;
      $table_name   = $wpdb->prefix.CF7PDF_TABLE;
      $results      = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_post_id = $form_post_id ORDER BY form_id", OBJECT);
      $first_row    = isset($results[0]) ? unserialize( $results[0]->form_value ): 0 ;
      $columns      = array();

      if( !empty($first_row) ){
         $columns['cb']      = '<input type="checkbox" />';
         foreach ($first_row as $key => $value) {

            if( $key == 'cf7pdf_status' ) continue;

            $key_val       = str_replace( array('your-'), '', $key);
            $columns[$key] = ucfirst( $key_val );
            $this->column_titles[] = $key_val;

            if ( sizeof($columns) > 3) break;
         }

         $columns['form-date'] = 'Date';
         $columns['action'] = 'View';
         $columns['download_pdf'] = 'Download pdf';
      }
      return $columns;
   }
    

   public function column_cb($item){
      return sprintf(
         '<input type="checkbox" name="%1$s[]" value="%2$s" />',$this->_args['singular'],$item['form_id']
      );
   }
    

   public function get_hidden_columns() {
      return  array('form_id');
   }
    

   public function get_sortable_columns() {
      return array('form-date' => array('form-date', true));
   }
    

   public function get_bulk_actions() {
      return array(
         'read'   => __( 'Read', 'cf7wpay' ),
         'unread' => __( 'Unread', 'cf7wpay' ),
         'delete' => __( 'Delete', 'cf7wpay' )
      );
   }


   private function table_data(){
      $data = array();
      global $wpdb;

   
      $table_name   = $wpdb->prefix.CF7PDF_TABLE;
      $form_post_id = $this->form_post_id;

      $orderby = isset($_GET['orderby']) ? 'form_date' : 'form_id';
      $order   = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
      $order   = esc_sql($order);

      

      $results = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_post_id = $form_post_id ORDER BY $orderby $order", OBJECT );
      
        
      foreach ( $results as $result ) {
         $form_value = unserialize( $result->form_value );

         $link = "<b><a href=admin.php?page=".CF7PDF_PAGE_SLUG."&cf7pdf_formid=%s&cf7pdf_entryid=%s>%s</a></b>";

         if(isset($form_value['cf7pdf_status']) && ( $form_value['cf7pdf_status'] === 'read' ) )
            $link  = "<a href=admin.php?page=".CF7PDF_PAGE_SLUG."&cf7pdf_formid=%s&cf7pdf_entryid=%s>%s</a>";

         $cf7pdf_formid            = $result->form_post_id;
         $form_values['form_id']   = $result->form_id;

         foreach ( $this->column_titles as $col_title) {
            $form_value[ $col_title ] = isset( $form_value[ $col_title ] ) ? $form_value[ $col_title ] : '';
         }

         foreach ($form_value as $k => $value) {
            $ktmp = $k;
            $can_foreach = is_array($value) || is_object($value);

            if ( $can_foreach ) {
               foreach ($value as $k_val => $val):
                  $val                = esc_html( $val );
                  $form_values[$ktmp] = ( strlen($val) > 150 ) ? substr($val, 0, 150).'...': $val;
                  $form_values[$ktmp] = $form_values[$ktmp];

               endforeach;
            }else{
               $value = esc_html( $value );
               $form_values[$ktmp] = ( strlen($value) > 150 ) ? substr($value, 0, 150).'...': $value;
               $form_values[$ktmp] = $form_values[$ktmp];
            }
         }

         $form_values['form-date']    = $result->form_date;
         $form_values['action']       = sprintf($link, $cf7pdf_formid, $result->form_id, 'View');
         $form_values['download_pdf'] = '<a href="?action=pdf_callback&form_id='.$cf7pdf_formid.'&pdf_id='.$result->form_id.'"><img src="'.CF7PDF_PLUGIN_DIR.'/includes/images/pdf.png"></a>';
         $data[] = $form_values;
      }
      return $data;
   }
  

   public function process_bulk_action(){

      global $wpdb;
      $table_name = $wpdb->prefix.CF7PDF_TABLE;
      $action     = $this->current_action();
      if(isset($_POST['contact_form'])) {
         $form_ids   = esc_sql( $_POST['contact_form'] );
      }
      if ( isset( $_POST['_wpnonce'] ) && ! empty( $_POST['_wpnonce'] ) ) {
         $nonce        = filter_input( INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING );
         $nonce_action = 'bulk-' . $this->_args['plural'];
         if ( !wp_verify_nonce( $nonce, $nonce_action ) ){
            wp_die( 'Not valid..!!' );
         }
      }


      if( 'delete' === $action ) {
         
         foreach ($form_ids as $form_id):
            $results       = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_id = $form_id LIMIT 1", OBJECT );
            $result_value  = $results[0]->form_value;
            $result_values = unserialize($result_value);
            $upload_dir    = wp_upload_dir();
            $cf7wpay_dirname = $upload_dir['basedir'].'/'.CF7PDF_UPLOAD;
            foreach ($result_values as $key => $result) {
               if ( file_exists($cf7wpay_dirname.'/'.$result) ) {
                  unlink($cf7wpay_dirname.'/'.$result);
               }
            }

            $wpdb->delete($table_name ,array( 'form_id' => $form_id ),array( '%d' ));
         endforeach;
         ?>
         <script type="text/javascript">
            window.location.reload();
         </script>
         <?php

      }else if( 'read' === $action ){

         foreach ($form_ids as $form_id):
            $results       = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_id = '$form_id' LIMIT 1", OBJECT );
            $result_value  = $results[0]->form_value;
            $result_values = unserialize( $result_value );
            $result_values['cf7pdf_status'] = 'read';
            $form_data = serialize( $result_values );
            $wpdb->query("UPDATE $table_name SET form_value = '$form_data' WHERE form_id = '$form_id'");
         endforeach;
         ?>
         <script type="text/javascript">
            window.location.reload();
         </script>
         <?php

      }else if( 'unread' === $action ){

         foreach ($form_ids as $form_id):
            $results       = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_id = '$form_id' LIMIT 1", OBJECT );
            $result_value  = $results[0]->form_value;
            $result_values = unserialize( $result_value );
            $result_values['cf7pdf_status'] = 'unread';
            $form_data = serialize( $result_values );
            $wpdb->query("UPDATE $table_name SET form_value = '$form_data' WHERE form_id = '$form_id'");
         endforeach;
         ?>
         <script type="text/javascript">
            window.location.reload();
         </script>
         <?php
      }else{

      }
   }
    
    
   public function column_default( $item, $column_name ){
      return $item[ $column_name ];
   }


   private function sort_data( $a, $b ){
      $orderby = 'form_date';
      $order = 'asc';
        
      if(!empty($_GET['orderby'])) {
         $orderby = sanitize_text_field( $_GET['orderby']);
      }
        
      if(!empty($_GET['order'])) {
         $order = sanitize_text_field($_GET['order']);
      }

      $result = strcmp( $a[$orderby], $b[$orderby] );
      if($order === 'asc') {
         return $result;
      }
      return -$result;
   }
    

   protected function bulk_actions( $which = '' ) {
      if ( is_null( $this->_actions ) ) {
         $this->_actions = $this->get_bulk_actions();
         $this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions );
         $two = '';
      } else {
         $two = '2';
      }

      if ( empty( $this->_actions ) ) return;

      echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' . __( 'Select bulk action', 'cf7wpay' ) . '</label>';
      echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";
         echo '<option value="-1">' . __( 'Bulk Actions', 'cf7wpay' ) . "</option>\n";

         foreach ( $this->_actions as $name => $title ) {
            $class = 'edit' === $name ? ' class="hide-if-no-js"' : '';
            echo "\t" . '<option value="' . $name . '"' . $class . '>' . $title . "</option>\n";
         }
      echo "</select>\n";

      submit_button( __( 'Apply', 'cf7wpay' ), 'action', '', false, array( 'id' => "doaction$two" ) );
      echo "\n";
   }
}
/*=========== end sub cf7 list ================*/



class cf7pdf_entry_details{
   private $form_id;
   private $form_post_id;

   public function __construct(){
      $this->form_post_id = esc_sql(sanitize_text_field( $_GET['cf7pdf_formid'] ));
      $this->form_id = esc_sql( sanitize_text_field($_GET['cf7pdf_entryid'] ));
      $this->form_details_page();
   }


   public function form_details_page(){
      global $wpdb;
      $table_name    = $wpdb->prefix.CF7PDF_TABLE;
      $upload_dir    = wp_upload_dir();
      $cf7pdf_dir_url = $upload_dir['baseurl'].'/'.CF7PDF_UPLOAD;


      if ( is_numeric($this->form_post_id) && is_numeric($this->form_id) ) {

         $results    = $wpdb->get_results( "SELECT * FROM $table_name WHERE form_post_id = $this->form_post_id AND form_id = $this->form_id LIMIT 1", OBJECT );
      }

      if ( empty($results) ) {
         wp_die( $message = 'Not valid contact form' );
      }

      ?>
      <div class="wrap">
         <div id="welcome-panel" class="welcome-panel">
            <div class="welcome-panel-content">
               <div class="welcome-panel-column-container">
                        
                  <h2><?php echo get_the_title( $this->form_post_id ); ?></h2> 
                  <p><span><?php echo $results[0]->form_date; ?></span></p>
                  <table>
                     <?php 
                        $form_data  = unserialize( $results[0]->form_value );
                        foreach ($form_data as $key => $data):
                           if ( $key == 'cf7pdf_status' )  continue;
                           echo "<tr>";
                              if ( $key == 'cf7pdf_status' )  continue;
                              $key_val       = str_replace( array('your-'), '', $key);
                              
                              echo '<td>'.ucfirst( $key_val ).'</td>';


                              $supported_image = array('gif', 'jpg', 'jpeg', 'png', 'pdf');

                              $ext = strtolower(pathinfo($data, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
                              if (in_array($ext, $supported_image)) {
                                 echo '<td><a href="'.$cf7pdf_dir_url.'/'.$data.'" target="_blank">'.$data.'</a></td>'; 
                              } else {
                                 echo '<td>'.$data.'</td>';
                              }
                           echo "</tr>";
                        endforeach;      
                     ?>
                  </table>
                  <?php
                     $form_data['cf7pdf_status'] = 'read';
                     $form_data = serialize( $form_data );
                     $form_id = $results[0]->form_id;
                     $wpdb->query( "UPDATE $table_name SET form_value = '$form_data' WHERE form_id = $form_id");
                  ?>
               </div>
            </div>
         </div>
      </div>
      <?php    
   }
}

