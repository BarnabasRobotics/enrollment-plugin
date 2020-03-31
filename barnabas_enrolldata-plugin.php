<?php
/*
Plugin Name: Enroll Data Plugin for barnabasrobotics.com
Description: Site specific code changes for barnabasrobotics.com
*/
// Disable direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register and load the widget
function bre_load_widget() {
    register_widget( 'bre_widget' );
}

add_action( 'widgets_init', 'bre_load_widget' );
 
// Creating the widget 
class bre_widget extends WP_Widget {
 
    function __construct() {
        parent::__construct(
            // Base ID of your widget
            'bre_widget', 
            // Widget name will appear in UI
            __('Barnabas Enroll Widget', 'bre_widget_domain'), 
            // Widget description
            array( 'description' => __( 'Use course ID to get data', 'bre_widget_domain' ), ) 
        );
        add_action ( 'admin_enqueue_scripts', 'bre_load_scripts');
    } // end construct

    function bre_load_scripts() {
        wp_enqueue_media ();
        wp_enqueue_script('jqm', plugin_dir_url( __FILE__ ) . 'jqm.js', array('jquery'));
    }    
        
    public function widget( $args, $instance ) {
        $output = '';
        $title = apply_filters( 'widget_title', $instance['title'] );
        $imgid =(isset( $instance[ 'imgid' ] )) ? $instance[ 'imgid' ] : "";
        $img    = wp_get_attachment_image_src($imgid, 'thumbnail');
		$course_name =(isset( $instance[ 'name' ] )) ? $instance[ 'name' ] : "This class";

        $response = wp_remote_get( 'https://enroll.barnabasrobotics.com/courses/'.$title.'/info.json' );
        if ( is_wp_error( $response ) ) {
            return;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ) );

        $response = wp_remote_get( 'https://enroll.barnabasrobotics.com/courses/'.$title.'/schedule.json' );
        if ( is_wp_error( $response ) ) {
            return;
        }
        $schedule = json_decode( wp_remote_retrieve_body( $response ) );


        if ( ! isset( $data->error ) ){
            $title = apply_filters( 'widget_title', $data->title );
            $start = date_create($data->start_date);
            $end = date_create($data->end_date);
        
            $interval = date_diff($start, $end);
            $weeks = $interval->format('%a');
            $weeks = (int)$weeks / 7;

            $output .= '<p style="font-size: 12pt"><mark>&nbsp;&nbsp;Ages '. $data->ages . '&nbsp;&nbsp;</mark></p>';
            $output .= '<p><strong>'.$data->name . '</strong></p><p>';
			$dsow = 0;
            if($data->sunday)
            	$dsow += 1;
            if($data->monday)
                $dsow += 1;
            if($data->tuesday)
                $dsow += 1;
            if($data->wednesday)
                $dsow += 1;
            if($data->thursday)
                $dsow += 1;
            if($data->friday)
                $dsow += 1;
            if($data->saturday)
                $dsow += 1;
            $sestype = $dsow > 1 ? ' Sessions' : ' Weeks';
            $output .= $schedule->days_of_the_week;
            $output .= ', <time>'.$data->start_time.'</time> - <time>'.$data->end_time.'</time><br />';
			$output .= '<time id="'.$title.'-start" datetime="'.$data->start_date.'">'.date_format($start,'F j').'</time> – <time id="'.$title.'-end" datetime="'.$data->end_date.'">'.date_format($end,'F j').'</time><br>';
            // $output .= $weeks.' weeks</p><p><em>'.strip_tags($data->schedule_notes).'</em></p>';
            $output .= count($schedule->session_dates).$sestype.'</p><p><em>'.strip_tags($data->schedule_notes).'</em></p>';

            $amt = absint($data->cost+$data->charter_fee);
            $output .= '<p>$'.$amt; 
            // $output .= ' <span class="materials">(materials included)</span>';
            if ($data->charter_fee > 0) {
                $output .= '<a class="discount" onclick="dialog(\'discount_'.$data->id. '\', \'Like Discounts?\',\'https://enroll.barnabasrobotics.com/course_registrations/new?course_id='.$data->id.'\')"> (Discount Available)</a>';
                $output .= '<div id="discount_'.$data->id.'" style="display:none">
				 Get <strong>$'.absint($data->charter_fee) .'</strong> off this class by paying the full tuition with credit card before the first day of class.</div>';
			} else {
                $output .= '<span class="no_discount"> (No Discount)</span>';
            }

            $output .= '</p><p class="seats">' . $data->seats . ' seats available</p>';


            $output .= '<div id="desc_'.$data->id.'" style="display:none">'; // .strip_tags($data->description).
            
            $output .=  <<<HTML
<div class='well'>
$data->description
</div>
<table class='table table-bordered table-striped table-hover'>
<tr>
<th>Contact</th>
<td>$data->contact</td>
</tr>
<tr>
<th>Prerequisites</th>
<td>$data->prerequisites</td>
</tr>
<tr>
<th>Class Size</th>
<td>
$data->class_size
</td>
</tr>
<tr>
<th>Charters</th>
<td>
<p>
<span class="accepted-charters-title">We accept charter funds from the following schools:</span>
&nbsp;
Blue Ridge Academy, Cabrillo Point Academy, California Enrichment Academy, Compass Charter Schools, Epic Charter Schools, Excel Academy, Golden Valley Charter School, Gorman Learning Center, Granite Mountain Charter School, Heartland Charter School, iLEAD, Mission Vista Academy, National University Academy, Peak Prep Pleasant Valley, Sage Oak Charter School, Sky Mountain Charter School
</p>
</td>
</tr>
<tr>
<th>Notes</th>
<td>
$data->notes
</td>
</tr>
<tr class="danger">
<th>Cancel Deadline</th>
<td>
$data->cancel_deadline
</td>
</tr>
</table>
HTML;

            $output .= '</div>'; // end modal 
            

            $output .= '<p><a onclick="dialog(\'desc_'.$data->id. '\', \''. $data->title .'\')">More Details</a></p>';
			// $output .= '<p><a href="https://enroll.barnabasrobotics.com/courses/'.$data->id.'/info" target="blank">Class Details</a></p>';
            if ($data->unregisterable)
			{
				$output .= '<p><button class="button coming-soon" disabled="disabled">Coming Soon</button></p>';
			} else {
				$output .= '<p><a href="https://enroll.barnabasrobotics.com/course_registrations/new?course_id='.$data->id.'" target="blank"><button class="button">Sign Up</button></a></p>';
			}
			
        } else {
            $output .= '<p class="nolongeravailable">'. $course_name . ' is no longer available</p>';
        }
		
		if( strpos($args['before_widget'], 'style') === false ) {
            $args['before_widget'] = str_replace('>', ' style="text-align: center;">', $args['before_widget']);
        }

        echo $args['before_widget'];

        if (! empty ( $img ))
        echo '<a href="https://enroll.barnabasrobotics.com/course_registrations/new?course_id='.$data->id.'" target="blank"><img src="'.$img[0].'" alt="class icon" width="84"/></a>';

        if ( ! empty( $title ) ) {
			$title = substr_replace($title, '<br/>', strrpos($title,' '),0);
			echo $args['before_title'] .'<span style="color: #78bc00;">'. $title . '</span>'. $args['after_title'];

		}
        
        echo __( $output, 'bre_widget_domain');

        echo $args['after_widget'];
    } // end widget
            
    // Widget Backend 
    public function form( $instance ) {
        if ( isset( $instance[ 'title' ] ) ) {
            $title = $instance[ 'title' ];
        }
        else {
            $title = __( '000', 'bre_widget_domain' );
        }
		
		if (isset( $instance[ 'name' ] )) {
            $name = $instance[ 'name' ];
        }
        else {
            $name = __( 'No name set', 'bre_widget_domain' );
        }

        if (isset( $instance[ 'imgid' ] )) {
            $imgid = $instance[ 'imgid' ];
            $img   = wp_get_attachment_image_src($imgid, 'thumbnail');
        }
        else {
            $upload_dir = wp_upload_dir(); // Array of key => value pairs
            $imgid = "";
            $img[0]= $upload_dir['baseurl'].'/2019/11/Barnabas-Bot-class-icon.png';
        }

        $response = wp_remote_get( 'https://enroll.barnabasrobotics.com/courses.json?search[city]' );
        if ( is_wp_error( $response ) ) {
            return;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ) );
        
        // Widget admin form
        ?>
        <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Course ID:' );?></label> 
        <select class="widefat set_course" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>">
        <?php foreach ($data as $city) { ?>
            <optgroup label="<?php echo $city[0]; ?>">
        <?php foreach ($city[1] as $course){
				$display = $course->name." - ".$course->title." | ".$course->start_date.", ".$course->start_time;
                if ( $course->id == esc_attr( $title )) { ?>
                    <option value="<?php echo $course->id; ?>" selected><?php echo $display; ?></option>
                <?php } else { ?>
                    <option value="<?php echo $course->id; ?>"><?php echo $display; ?></option>
                <?php }
					}?>
            </optgroup>
        <?php } ?>
        </select>
        </p>
			<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'name' ) ); ?>"><?php _e( 'Name:', 'bre_widget_domain' ); ?></label>
		<input disabled class="widefat course_text" id="<?php echo esc_attr( $this->get_field_id( 'name' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'name' ) ); ?>" type="text" value="<?php echo esc_attr( $name ); ?>" />
	</p>
 
        <p>
            <input class="widefat" hidden id="<?php echo $this->get_field_id( 'imgid' ); ?>" name="<?php echo $this->get_field_name( 'imgid' ); ?>" type="number" value="<?php echo esc_attr( $imgid ); ?>" />
            <button class="set_custom_images button">Select Image</button>
            <img src="<?= $img[0]; ?>" width="84px" />
        </p>
        <script>
        (function($){

        "use strict";
			
		if ($('.set_course').value != '000'){
			$('.set_course').on('change', function(e) {
				e.preventDefault();
				var str =  $('.set_course option:selected').text();
				var start_pos =str.indexOf('-') + 2;
				var end_pos = str.indexOf('|') - 1;
				var name = str.substring(start_pos,end_pos)
				$('.course_text').val(name);
				return false;
			})
		}
        if ($('.set_custom_images').length > 0) {
            if ( typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                $('.set_custom_images').on('click', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var id = button.prev();
                    var pic = button.next();
                    wp.media.editor.send.attachment = function(props, attachment) {
                        id.val(attachment.id);
                        pic.attr('src', attachment.url);
                    };
                    wp.media.editor.open(button);
                    return false;
                });
            }
        }
        })(jQuery);
        </script>

        <?php 
    } // end form 
        
    // Updating widget replacing old instances with new
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['imgid'] = ( ! empty( $new_instance['imgid'] ) ) ? strip_tags( $new_instance['imgid'] ) : '';
		$instance['name'] = ( ! empty( $new_instance['name'] ) ) ? strip_tags( $new_instance['name'] ) : '';
        return $instance;
    } // end update
}//end class
?>
