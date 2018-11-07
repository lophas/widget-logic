<?php
/*
    Plugin Name: Widgetmaster Widget Logic
    Description: Control widgets with WP's conditional tags is_home etc
    Version: 0.3
    Plugin URI: https://github.com/lophas/widget-logic
    GitHub Plugin URI: https://github.com/lophas/widget-logic
    Author: Attila Seres
    Author URI:
*/

if (!class_exists('widget_logic')) :
class widget_logic
{
    protected $disabled_sidebars_widgets = [];

    private static $_instance;
    public function instance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance =  new self();
        }
        return self::$_instance;
    }

    protected function __construct()
    {
        if (is_admin()) {
            add_action('in_widget_form', array( $this, 'in_widget_form' ), PHP_INT_MAX-1, 3);
            add_filter('widget_update_callback', array($this,'widget_update_callback'), 10, 4);
        } else {
            add_action('wp_enqueue_scripts', array($this,'init'), 1);
        }
        add_action('plugins_loaded', array($this,'check_conflicting_versions'), PHP_INT_MAX-1);
    }

    public function init()
    {
        $wp_sidebars_widgets = wp_get_sidebars_widgets();

        if (!empty($wp_sidebars_widgets)) {
            $wl_options = get_option('widget_logic', array());
            $instances=array();
            foreach ($wp_sidebars_widgets as $sid=>$widgets) {
                if ($sid=='wp_inactive_widgets') {
                    continue;
                }
                if (empty($widgets) || !is_array($widgets)) {
                    continue;
                }
                foreach ($widgets as $pos=>$id) {
                    $id_base=substr($id, 0, strrpos($id, '-'));
                    $number=substr($id, strrpos($id, '-')+1);
                    if (!is_numeric($number)) {
                        continue;
                    }
                    if (!isset($instances[$id_base])) {
                        $instances[$id_base]=get_option('widget_'.$id_base);
                    }
                    if ($instance=$instances[$id_base][$number]) {
                        if (isset($instance['wl_value'])) {
                            $wl_value=stripslashes(trim($instance['wl_value']));
                        } else {
                            $wl_value=stripslashes(trim($wl_options[$id]));
                        }
                        if (!empty($wl_value)) {
                            if (stristr($wl_value, "return")===false) {
                                $wl_value="return (" . $wl_value . ");";
                            }
                            if (!eval($wl_value)) {
                                $this->disabled_sidebars_widgets[$sid][] = $id;
                                continue;
                            }
                        }
                    }
                }
            }
        }
        if (!empty($this->disabled_sidebars_widgets)) {
            add_filter('sidebars_widgets', array($this,'sidebars_widgets'), PHP_INT_MAX-1);
        }
    }

    public function sidebars_widgets($sidebars_widgets)
    {
        foreach ($this->disabled_sidebars_widgets as $sid=>$widgets) {
            $sidebars_widgets[$sid] = array_diff($sidebars_widgets[$sid], $widgets);
        }
        return $sidebars_widgets;
    }

    public function widget_update_callback($instance, $new_instance, $old_instance, $widget)
    {
        if (isset($new_instance['wl_value'])) {
            $instance['wl_value'] = $new_instance['wl_value'];
        }
        return $instance;
    }


    public function in_widget_form($widget, $return, $instance)
    {
        if (!isset($instance['wl_value'])) {
            $wl_options = get_option('widget_logic', array());
            $wl_value     = isset($wl_options[$widget->id]) ? $wl_options[$widget->id] : '';
        } else {
            $wl_value = $instance['wl_value'];
        } ?>
				<label for="<?php echo $widget->get_field_id('wl_value'); ?>"><?php _e('Widget Logic:'); ?></label>
				<textarea rows="1" class="widefat" id="<?php echo $widget->get_field_id('wl_value'); ?>" name="<?php echo $widget->get_field_name('wl_value'); ?>"><?php echo esc_attr($wl_value); ?></textarea>

<?php
    }
    public function check_conflicting_versions()
    {
        if (!function_exists('widget_logic_widget_display_callback')) {
            return;
        }
        if (is_admin()) {
            remove_filter('widget_update_callback', 'widget_logic_ajax_update_callback', 10, 3);
            remove_action('sidebar_admin_setup', 'widget_logic_expand_control');
            remove_action('sidebar_admin_page', 'widget_logic_options_control');
            remove_filter('in_widget_form', 'widget_logic_in_widget_form', 10, 3);
            remove_filter('widget_update_callback', 'widget_logic_update_callback', 10, 4);
            remove_filter('plugin_action_links', 'wl_charity', 10, 2);
            remove_action('widgets_init', 'widget_logic_add_controls', 999);
        } else {
            remove_filter('sidebars_widgets', 'widget_logic_filter_sidebars_widgets', 10);
            remove_filter('dynamic_sidebar_params', 'widget_logic_widget_display_callback', 10);
            remove_action('after_setup_theme', 'widget_logic_sidebars_widgets_filter_add');
            remove_action('wp_loaded', 'widget_logic_sidebars_widgets_filter_add');
            remove_action('wp_head', 'widget_logic_sidebars_widgets_filter_add');
            remove_action('parse_query', 'widget_logic_sidebars_widgets_filter_add');
        }
    }
}
widget_logic::instance();
endif;
