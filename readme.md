# Front End CRUD

Primeiro teste para desenvolvedor front end júnior da abril (BOLD International)

## Começando

Essas instruções vão mostrar como copiar os arquivos do projeto, o que é necessário para fazer ele rodar na sua máquina e também vai conter uma breve explicação do código.

### Pré-requisitos

Você vai precisar do wordpress instalado e rodando

* [Wordpress](https://wordpress.org/) - Faça o download do Wordpress

### Informações

Enunciado do teste para referência:

```
Fazer um sistema de controle de estoque.

Tendo em vista as tabelas:

Produto(id,nome,descrição,preço)
Cliente(id,nome,email,telefone)
Pedido(id_produto, id_cliente)

Fazer o download do wordpress: https://wordpress.org/download/

Criar um CRUD 'no wordpress, utilizando um postType para cada modelo, ou seja, um postType com um customFields para Produto, Cliente, Pedido.
```

O projeto foi realizado com algumas adaptações:
```
Os nomes das tabelas com exceção da "pedido" estão em inglês
```
```
Alguns campos estão abreviados e outros em inglês
```
```
Foram feitos 3 plugins que funcionam em qualquer instalação do wordpress
Porém o plugin de pedido não funcionará corretamente se não existir os outros dois
```

### Instalando

Para instalar os plugins basta baixar as pastas e movê-las para o seguinte diretório do seu wordpress:

```
wp-content/plugins/
```

Depois disso vá até a aba "Plugins" no painel de controle do wordpress e ative os plugins
Quando ativados eles criarão as tabelas no banco de dados para que possam gravar os dados

## Acessando e utilizando

Após a instalação, no painel de controle do wordpress irá aparecer 3 novas abas:

```
Clientes
```
```
Pedidos
```
```
Produtos
```

### Considerações

As tabelas serão criadas assim que os plugins forem ativados, como no trecho abaixo:

```
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
```

Função para criação dos menus:

```
function pedidoManager_admin_menu() {
  add_menu_page(__('Pedidos', 'pedido'), __('Pedidos', 'pedido'), 'activate_plugins', 'pedidos', 'pedido_page_handler');
  add_submenu_page('pedidos', __('Adicionar', 'pedido'), __('Adicionar', 'pedido'), 'activate_plugins', 'pedido_form', 'pedido_form_page_handler');
}

add_action('admin_menu', 'pedidoManager_admin_menu');
```

### Notas dessa versão

Alguns pontos ficaram em abertos e podem ser melhorados:
* mascara para os campos:
  * telefone do cliente
  * email do cliente
  * preço do produto
* criação mais inteligente dos campos do tipo select