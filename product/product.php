<?php
	/*
	Plugin Name: Product Manager | Danilo Rodrigues
	Plugin URI: http://localhost/abril/teste1/wp-content/plugin/product
	Description: Plugin that implements a CRUD for products management
	Author: Danilo
	Version: 1.0
	Author URI: https://github.com/daniro-san
	*/

	function productManager_install() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'product';

			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL AUTO_INCREMENT,
				name text NOT NULL,
				description VARCHAR(255),
				price VARCHAR(255) NOT NULL,
				PRIMARY KEY  (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
	}
	register_activation_hook(__FILE__, 'productManager_install');

	if (!class_exists('WP_List_Table')) {
		require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
	}

	class ProductManager_List_Table extends WP_List_Table {
		function __construct() {
			global $status, $page;

			parent::__construct(array(
				'singular' => 'produto',
				'plural' => 'produtos',
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
				'edit' => sprintf('<a href="?page=produto_form&id=%s">%s</a>', $item['id'], __('Editar', 'produto')),
				'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Excluir', 'produto')),
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
				'id' => __('Código', 'produto'),
				'name' => __('Produto', 'produto'),
				'description' => __('Descrição', 'produto'),
				'price' => __('Preço', 'produto'),
			);
			return $columns;
		}


		function get_sortable_columns() {
			$sortable_columns = array(
				'id' => array('id', true),
				'name' => array('name', false),
				'price' => array('price', false),
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
			$table_name = $wpdb->prefix . 'product';

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
			$table_name = $wpdb->prefix . 'product';
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

	function productManager_admin_menu() {
    add_menu_page(__('Produtos', 'produto'), __('Produtos', 'produto'), 'activate_plugins', 'produtos', 'produto_page_handler');
    add_submenu_page('produtos', __('Adicionar', 'produto'), __('Adicionar', 'produto'), 'activate_plugins', 'produto_form', 'produto_form_page_handler');
	}
	add_action('admin_menu', 'productManager_admin_menu');

	function produto_page_handler() {
		global $wpdb;

		$table = new ProductManager_List_Table();
		$table->prepare_items();

		$message = '';
		if ('delete' === $table->current_action()) {
			$message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Produtos deletados: %d', 'produto'), count($_REQUEST['id'])) . '</p></div>';
		}

		?>

		<div class="wrap">
			<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
			<h2>
				<?php _e('Produtos', 'produto')?> 
				<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=produto_form');?>"><?php _e('Adicionar', 'produto')?></a>
			</h2>

			<?php echo $message; ?>

			<form id="produto-table" method="GET">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<?php $table->display() ?>
			</form>
		</div>
		<?php
	}

	function produto_form_page_handler() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'product';

		$message = '';
		$notice = '';

		$default = array(
			'id' => 0,
			'name' => '',
			'description' => '',
			'price' => '',
		);

		if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
			$item = shortcode_atts($default, $_REQUEST);
			$item_valid = validate_table_produto($item);
			if ($item_valid === true) {
				if ($item['id'] == 0) {
					$result = $wpdb->insert($table_name, $item);
					$item['id'] = $wpdb->insert_id;
					if ($result) {
						$message = __('Produto adicionado com sucesso!', 'produto');
					} else {
						$notice = __('Ops! Ocorreu um erro ao salvar!', 'produto');
					}
				} else {
					$result = $wpdb->update($table_name, $item, array('id' => $item['id']));
					if ($result) {
						$message = __('Produto atualizado com sucesso!', 'produto');
					} else {
						$notice = __('Ops! Ocorreu um erro ao atualizar!', 'produto');
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
					$notice = __('Produto não encontrado', 'produto');
				}
			}
		}

		add_meta_box('produto_form_meta_box', 'Dados do Produto', 'produto_form_meta_box_handler', 'produto', 'normal', 'default');

		?>
		<div class="wrap">
			<div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
			<h2>
				<?php _e('Produto', 'produto')?> 
				<a class="add-new-h2" href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=produtos');?>"><?php _e('Voltar para a listagem', 'produto')?></a>
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
							<?php do_meta_boxes('produto', 'normal', $item); ?>
							<input type="submit" value="<?php _e('Salvar', 'produto')?>" id="submit" class="button-primary" name="submit">
						</div>
					</div>
				</div>
			</form>
		</div>
	<?php
	}

	function produto_form_meta_box_handler($item) { ?>
		<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
			<tbody>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="name"><?php _e('Produto', 'produto')?></label>
					</th>
					<td>
						<input id="name" name="name" type="text" style="width: 95%" value="<?php echo esc_attr($item['name'])?>" size="50" class="code" placeholder="<?php _e('Produto', 'produto')?>" required>
					</td>
				</tr>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="description"><?php _e('Descrição', 'produto')?></label>
					</th>
					<td>
						<textarea id="description" name="description" style="width: 95%" size="50" class="code" placeholder="<?php _e('Descrição Produto', 'produto')?>"><?php echo esc_attr($item['description'])?></textarea>
					</td>
				</tr>
				<tr class="form-field">
					<th valign="top" scope="row">
						<label for="price"><?php _e('Preço', 'produto')?></label>
					</th>
					<td>
						<input id="price" name="price" type="text" style="width: 95%" value="<?php echo esc_attr($item['price'])?>" size="50" class="code" placeholder="<?php _e('Ex: R$ 150,00', 'produto')?>" required>
					</td>
				</tr>
			</tbody>
		</table>
	<?php
	}

	function validate_table_produto($item) {
		$messages = array();

		if(empty($item['name'])) $messages[] = __('Informe o produto!', 'produto');
		if(empty($item['price'])) $messages[] = __('Informe o preço!', 'produto');
		
		if (empty($messages)) return true;
		return implode('<br />', $messages);
	}
?>