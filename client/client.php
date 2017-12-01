<?php
	/*
	Plugin Name: Client Manager | Danilo Rodrigues
	Plugin URI: http://localhost/abril/teste1/wp-content/plugin/client
	Description: Plugin that implements a CRUD for clients management
	Author: Danilo
	Version: 1.0
	Author URI: https://github.com/daniro-san
	*/

	function clientManager_install() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'client';

			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL AUTO_INCREMENT,
				name text NOT NULL,
				email VARCHAR(255) NOT NULL,
				tel VARCHAR(40) NOT NULL,
				PRIMARY KEY  (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
	}
	register_activation_hook(__FILE__, 'clientManager_install');

	if (!class_exists('WP_List_Table')) {
		require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
	}

	class ClientManager_List_Table extends WP_List_Table {
		function __construct() {
			global $status, $page;

			parent::__construct(array(
				'singular' => 'cliente',
				'plural' => 'clientes',
			));
		}

		function column_default($item, $column_name) {
			return $item[$column_name];
		}

		// function column_price($item) {
		// 	return '<em> R$' . number_format($item['price'], 2, ',', '.') . '</em>';
		// }

		function column_name($item) {
			$actions = array(
				'edit' => sprintf('<a href="?page=cliente_form&id=%s">%s</a>', $item['id'], __('Editar', 'cliente')),
				'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Excluir', 'cliente')),
			);

			return sprintf('%s %s',
				$item['name'],
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
				'id' => __('Código', 'cliente'),
				'name' => __('Cliente', 'cliente'),
				'email' => __('Descrição', 'cliente'),
				'tel' => __('Telefone', 'cliente'),
			);
			return $columns;
		}


		function get_sortable_columns() {
			$sortable_columns = array(
				'id' => array('id', true),
				'name' => array('name', false),
				'email' => array('price', false),
				'tel' => array('price', false),
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
			$table_name = $wpdb->prefix . 'client';

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
			$table_name = $wpdb->prefix . 'client';
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
			$this->items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $paged), ARRAY_A);
			$this->set_pagination_args(array(
				'total_items' => $total_items,
				'per_page' => $per_page,
				'total_pages' => ceil($total_items / $per_page)
			));
		}
	}

	function clientManager_admin_menu() {
    add_menu_page(__('Clientes', 'cliente'), __('Clientes', 'cliente'), 'activate_plugins', 'clientes', 'cliente_page_handler');
    add_submenu_page('clientes', __('Adicionar', 'cliente'), __('Adicionar', 'cliente'), 'activate_plugins', 'cliente_form', 'cliente_form_page_handler');
	}
	add_action('admin_menu', 'clientManager_admin_menu');

	function cliente_page_handler() {
		global $wpdb;

		$table = new ClientManager_List_Table();
		$table->prepare_items();

		$message = '';
		if ('delete' === $table->current_action()) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Clientes deletados: %d', 'cliente'), count($_REQUEST['id'])) . '</p></div>';
		}

		?>

		<div class="wrap">
			<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
			<h2>
				<?php _e('Clientes', 'cliente')?> 
				<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=cliente_form');?>"><?php _e('Adicionar', 'cliente')?></a>
			</h2>

			<?php echo $message; ?>

			<form id="cliente-table" method="GET">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<?php $table->display() ?>
			</form>
		</div>
		<?php
	}

	function cliente_form_page_handler() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'client';

		$message = '';
		$notice = '';

		$default = array(
			'id' => 0,
			'name' => '',
			'email' => '',
			'tel' => '',
		);

		if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
			$item = shortcode_atts($default, $_REQUEST);
			$item_valid = validate_table_cliente($item);
			if ($item_valid === true) {
				if ($item['id'] == 0) {
					$result = $wpdb->insert($table_name, $item);
					$item['id'] = $wpdb->insert_id;
					if ($result) {
						$message = __('Cliente adicionado com sucesso!', 'cliente');
					} else {
						$notice = __('Ops! Ocorreu um erro ao salvar!', 'cliente');
					}
				} else {
					$result = $wpdb->update($table_name, $item, array('id' => $item['id']));
					if ($result) {
						$message = __('Cliente atualizado com sucesso!', 'cliente');
					} else {
						$notice = __('Ops! Ocorreu um erro ao atualizar!', 'cliente');
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
					$notice = __('Cliente não encontrado', 'cliente');
				}
			}
		}

		add_meta_box('cliente_form_meta_box', 'Dados do Cliente', 'cliente_form_meta_box_handler', 'cliente', 'normal', 'default');

		?>
		<div class="wrap">
			<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
			<h2>
				<?php _e('Cliente', 'cliente')?> 
				<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=clientes');?>"><?php _e('Voltar para a listagem', 'cliente')?></a>
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
							<?php do_meta_boxes('cliente', 'normal', $item); ?>
							<input type="submit" value="<?php _e('Salvar', 'cliente')?>" id="submit" class="button-primary" name="submit">
						</div>
					</div>
				</div>
			</form>
		</div>
	<?php
	}

	function cliente_form_meta_box_handler($item) { ?>
		<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
			<tbody>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="name"><?php _e('Cliente', 'cliente')?></label>
					</th>
					<td>
						<input id="name" name="name" type="text" style="width: 95%" value="<?php echo esc_attr($item['name'])?>" size="50" class="code" placeholder="<?php _e('Cliente', 'cliente')?>" required>
					</td>
				</tr>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="email"><?php _e('E-mail', 'cliente')?></label>
					</th>
					<td>
						<input id="email" name="email" type="email" value="<?php echo esc_attr($item['email'])?>" style="width: 95%" size="50" class="code" placeholder="<?php _e('Email cliente', 'cliente')?>" required>
					</td>
				</tr>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="tel"><?php _e('Telefone', 'cliente')?></label>
					</th>
					<td>
						<input id="tel" name="tel" type="text" style="width: 95%" value="<?php echo esc_attr($item['tel'])?>" size="50" class="code" placeholder="<?php _e('Telefone cliente', 'cliente')?>" required>
					</td>
				</tr>
			</tbody>
		</table>
	<?php
	}

	function validate_table_cliente($item) {
		$messages = array();

		if(empty($item['name'])) $messages[] = __('Informe o cliente!', 'cliente');
		if (!empty($item['email']) && !is_email($item['email'])) $messages[] = __('O formato do email está incorreto!', 'cliente');
		if(empty($item['tel'])) $messages[] = __('Informe o telefone!', 'cliente');
		
		if (empty($messages)) return true;
		return implode('<br />', $messages);
	}
?>