<?php
	/*
	Plugin Name: Order Manager | Danilo Rodrigues
	Plugin URI: http://localhost/abril/teste1/wp-content/plugin/order
	Description: Plugin that implements a CRUD for orders management
	Author: Danilo
	Version: 1.0
	Author URI: https://github.com/daniro-san
	*/

	function pedidoManager_install() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'pedido';

			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL AUTO_INCREMENT,
				id_prd int(11) NOT NULL,
				id_cli int(11) NOT NULL,
				PRIMARY KEY  (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
	}
	register_activation_hook(__FILE__, 'pedidoManager_install');

	if (!class_exists('WP_List_Table')) {
		require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
	}

	class PedidoManager_List_Table extends WP_List_Table {
		function __construct() {
			global $status, $page;

			parent::__construct(array(
				'singular' => 'pedido',
				'plural' => 'pedidos',
			));
		}

		function column_default($item, $column_name) {
			return $item[$column_name];
		}

		// function column_price($item) {
		// 	return '<em> R$' . number_format($item['price'], 2, ',', '.') . '</em>';
		// }

		function column_id($item) {
			$actions = array(
				'edit' => sprintf('<a href="?page=pedido_form&id=%s">%s</a>', $item['id'], __('Editar', 'pedido')),
				'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Excluir', 'pedido')),
			);

			return sprintf('%s %s',
				$item['id'],
				$this->row_actions($actions)
			);
		}

		function column_cb($item) {
			return sprintf(
				'<input type="checkbox" name="id[]" value="%s" />',
				$item['id']
			);
		}

		function get_columns() {
			$columns = array(
				'cb' => '<input type="checkbox" />',
				'id' => __('Código', 'pedido'),
				'cliente' => __('Cliente', 'pedido'),
				'produto' => __('Produto', 'pedido'),
			);
			return $columns;
		}


		function get_sortable_columns() {
			$sortable_columns = array(
				'id' => array('id', true),
				'cliente' => array('cliente', false),
				'produto' => array('produto', false),
			);
			return $sortable_columns;
		}

		function get_bulk_actions() {
			$actions = array(
				'delete' => 'Excluir'
			);
			return $actions;
		}

		function process_bulk_action() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'pedido';

			if ('delete' === $this->current_action()) {
				$ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
				if (is_array($ids)) $ids = implode(',', $ids);

				if (!empty($ids)) {
					$wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
				}
			}
		}

		function prepare_items() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'pedido';
			$per_page = 5;
			$columns = $this->get_columns();
			$hidden = array();
			$sortable = $this->get_sortable_columns();
			$this->_column_headers = array($columns, $hidden, $sortable);
			$this->process_bulk_action();
			$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
			$paged = isset($_REQUEST['paged']) ? max(0, intval($_REQUEST['paged']) - 1) : 0;
			$orderby = (isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns()))) ? $_REQUEST['orderby'] : 'id';
      $order = (isset($_REQUEST['order']) && in_array($_REQUEST['order'], array('asc', 'desc'))) ? $_REQUEST['order'] : 'asc';
      $sql = "
        SELECT 
          o.*,
          (SELECT name from wp_client c where c.id = o.id_cli) as cliente,
          (SELECT name from wp_product p where p.id = o.id_prd) as produto
        FROM $table_name o
        ORDER BY $orderby $order LIMIT %d OFFSET %d
      ";
      $this->items = $wpdb->get_results($wpdb->prepare($sql, $per_page, $paged), ARRAY_A);
			$this->set_pagination_args(array(
				'total_items' => $total_items,
				'per_page' => $per_page,
				'total_pages' => ceil($total_items / $per_page)
			));
		}
	}

	function pedidoManager_admin_menu() {
    add_menu_page(__('Pedidos', 'pedido'), __('Pedidos', 'pedido'), 'activate_plugins', 'pedidos', 'pedido_page_handler');
    add_submenu_page('pedidos', __('Adicionar', 'pedido'), __('Adicionar', 'pedido'), 'activate_plugins', 'pedido_form', 'pedido_form_page_handler');
	}
	add_action('admin_menu', 'pedidoManager_admin_menu');

	function pedido_page_handler() {
		global $wpdb;

		$table = new PedidoManager_List_Table();
		$table->prepare_items();

		$message = '';
		if ('delete' === $table->current_action()) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Pedidos deletados: %d', 'pedido'), count($_REQUEST['id'])) . '</p></div>';
		}

		?>

		<div class="wrap">
			<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
			<h2>
				<?php _e('Pedidos', 'pedido')?> 
				<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=pedido_form');?>"><?php _e('Adicionar', 'pedido')?></a>
			</h2>

			<?php echo $message; ?>

			<form id="pedido-table" method="GET">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<?php $table->display() ?>
			</form>
		</div>
		<?php
	}

	function pedido_form_page_handler() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'pedido';

		$message = '';
		$notice = '';

		$default = array(
			'id' => 0,
			'id_prd' => '',
			'id_cli' => '',
		);

		if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
			$item = shortcode_atts($default, $_REQUEST);
			$item_valid = validate_table_pedido($item);
			if ($item_valid === true) {
				if ($item['id'] == 0) {
					$result = $wpdb->insert($table_name, $item);
					$item['id'] = $wpdb->insert_id;
					if ($result) {
						$message = __('Pedido adicionado com sucesso!', 'pedido');
					} else {
						$notice = __('Ops! Ocorreu um erro ao salvar!', 'pedido');
					}
				} else {
					$result = $wpdb->update($table_name, $item, array('id' => $item['id']));
					if ($result) {
						$message = __('Pedido atualizado com sucesso!', 'pedido');
					} else {
						$notice = __('Ops! Ocorreu um erro ao atualizar!', 'pedido');
					}
				}
			} else {
				$notice = $item_valid;
			}
		} else {
			$item = $default;
			if (isset($_REQUEST['id'])) {
				$item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
				if (!$item) {
					$item = $default;
					$notice = __('Pedido não encontrado', 'pedido');
				}
			}
		}

		add_meta_box('pedido_form_meta_box', 'Dados do Pedido', 'pedido_form_meta_box_handler', 'pedido', 'normal', 'default');

		?>
		<div class="wrap">
			<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
			<h2>
				<?php _e('Pedido', 'pedido')?> 
				<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=pedidos');?>"><?php _e('Voltar para a listagem', 'pedido')?></a>
			</h2>

			<?php if (!empty($notice)): ?>
				<div id="notice" class="error"><p><?php echo $notice ?></p></div>
			<?php endif;?>

			<?php if (!empty($message)): ?>
				<div id="message" class="updated"><p><?php echo $message ?></p></div>
			<?php endif;?>

			<form id="form" method="POST">
				<input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__)); ?>"/>
				<input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>
				<div class="metabox-holder" id="poststuff">
					<div id="post-body">
						<div id="post-body-content">
							<?php do_meta_boxes('pedido', 'normal', $item); ?>
							<input type="submit" value="<?php _e('Salvar', 'pedido')?>" id="submit" class="button-primary" name="submit">
						</div>
					</div>
				</div>
			</form>
		</div>
	<?php
	}

	function pedido_form_meta_box_handler($item) { 
    global $wpdb;
    $cli_data = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."client");
    $prd_data = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."product");

  ?>
		<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
			<tbody>
        <tr class="form-field">
          <th valign="top" scope="row">
            <label for="id_cli"><?php _e('Cliente', 'pedido')?></label>
          </th>
          <td>
            <select id="id_cli" name="id_cli" style="width: 95%" class="code" required>
              <option value="selecione">Selecione</option>
              <?php
                foreach ($cli_data as $v) {
                  echo '<option value="' . $v->id. '" ' . (esc_attr($item['id_cli']) == $v->id ? "selected" : "") . '>' . $v->name . '</option>';
                }
              ?>
            </select>
          </td>
				</tr>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="id_prd"><?php _e('Produto', 'pedido')?></label>
					</th>
					<td>
            <select id="id_prd" name="id_prd" style="width: 95%" class="code" required>
              <option value="selecione">Selecione</option>
              <?php
                foreach ($prd_data as $v) {
                  echo '<option value="' . $v->id . '" ' . (esc_attr($item['id_prd']) == $v->id ? "selected" : "") . '>' . $v->name . '</option>';
                }
              ?>
            </select>
					</td>
				</tr>
			</tbody>
		</table>
	<?php
	}

	function validate_table_pedido($item) {
		$messages = array();

		if($item['id_cli'] == 'selecione') $messages[] = __('Selecione um cliente válido!', 'pedido');
		if($item['id_prd'] == 'selecione') $messages[] = __('Selecione um produto válido!', 'pedido');
		
		if (empty($messages)) return true;
		return implode('<br />', $messages);
	}
?>