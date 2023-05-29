<?php
/**
 * Plugin Name: Histórico de Navegação WP
 * Description: Plugin para rastrear o histórico de navegação.
 * Version: 1.0
 * Author: Bruno Albim
 * Author URI: https://bruno.art.br
 */
 
// Função para criar a tabela no banco de dados
function criar_tabela_historico_navegacao() {
    global $wpdb;
    $nome_tabela = $wpdb->prefix . 'historico_navegacao';

    $sql = "CREATE TABLE IF NOT EXISTS $nome_tabela (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        dia DATE NOT NULL,
        horario TIME NOT NULL,
        titulo_pagina VARCHAR(255) NOT NULL,
        url_pagina TEXT NOT NULL
    )";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'criar_tabela_historico_navegacao');




// Função para rastrear e armazenar o histórico de navegação
function rastrear_historico_navegacao() {
    if (is_user_logged_in()) {
        global $wpdb;
        $nome_tabela = $wpdb->prefix . 'historico_navegacao';

        $user_id = get_current_user_id();
        $dia = date('Y-m-d');
        $horario = current_time('H:i:s');
        $titulo_pagina = '';
        $url_pagina = '';

        // Verifica se é a página inicial
        if (is_front_page()) {
            $url_pagina = home_url('/');
            $titulo_pagina = get_bloginfo('name');
        } elseif (is_singular()) { // Verifica se é uma página de post ou página
            $titulo_pagina = get_the_title();
            $url_pagina = get_permalink();
        } elseif (is_archive()) { // Verifica se é uma página de arquivo (categorias, tags, etc.)
            $titulo_pagina = single_term_title('', false);
            $url_pagina = get_term_link(get_queried_object());
        } elseif (is_search()) { // Verifica se é uma página de resultados de pesquisa
            $titulo_pagina = 'Resultados de Pesquisa: ' . get_search_query();
            $url_pagina = get_search_link();
        } else { // Página desconhecida
            $titulo_pagina = 'Página Desconhecida';
            $url_pagina = '';
        }

        $dados = array(
            'user_id' => $user_id,
            'dia' => $dia,
            'horario' => $horario,
            'titulo_pagina' => $titulo_pagina,
            'url_pagina' => $url_pagina
        );
        
        $registros_existentes = $wpdb->get_results("SELECT * FROM $nome_tabela WHERE user_id = $user_id ORDER BY dia ASC, horario ASC");
        $num_registros = count($registros_existentes);

        if ($num_registros >= 20) {
            // Remove o registro mais antigo
            $registro_mais_antigo = reset($registros_existentes);
            $wpdb->delete($nome_tabela, array('id' => $registro_mais_antigo->id));
        }

        $wpdb->insert($nome_tabela, $dados);
    }
}
add_action('wp', 'rastrear_historico_navegacao');

// Função para exibir o histórico de navegação na página de perfil do usuário
function exibir_historico_navegacao() {
    global $wpdb;
    $nome_tabela = $wpdb->prefix . 'historico_navegacao';
    $user_id = $_GET['user_id']?:get_current_user_id();
    

    $registros = $wpdb->get_results("SELECT * FROM $nome_tabela WHERE user_id = $user_id ORDER BY dia DESC, horario DESC");

    echo '<div id="historico_navegacao" class="wrap">';
    echo '<h1>Histórico de Navegação (Últimos 20 registros)</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Dia</th><th>Horário</th><th>Título da Página</th><th>URL da Página</th></tr></thead>';
    echo '<tbody>';

    foreach ($registros as $registro) {
        echo '<tr>';
        echo '<td>' . $registro->dia . '</td>';
        echo '<td>' . $registro->horario . '</td>';
        echo '<td>' . $registro->titulo_pagina . '</td>';
        echo '<td><a href="' . $registro->url_pagina . '" target="_blank">' . $registro->url_pagina . '</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

// Adicione a tabela de histórico de navegação na página de perfil do usuário
function adicionar_tabela_historico_navegacao() {
    add_action('show_user_profile', 'exibir_historico_navegacao');
    add_action('edit_user_profile', 'exibir_historico_navegacao');
}
add_action('admin_init', 'adicionar_tabela_historico_navegacao');




// Função para exibir o histórico de navegação na página do usuário
function exibir_historico_navegacao_geral() {
    global $wpdb;
    $nome_tabela = $wpdb->prefix . 'historico_navegacao';

    $registros = $wpdb->get_results("SELECT * FROM $nome_tabela ORDER BY dia DESC, horario DESC");
    
    echo '<div class="wrap">';
    echo '<h1>Histórico de Navegação</h1>';
    echo '<p><a href="' . esc_url(admin_url('admin-ajax.php?action=download_historico_navegacao')) . '" class="button">Download Histórico</a></p>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Dia</th><th>Horário</th><th>Usuário</th><th>Título da Página</th><th>URL da Página</th></tr></thead>';
    echo '<tbody>';

    foreach ($registros as $registro) {
        $user_data = get_userdata($registro->user_id);
        echo '<tr>';
        echo '<td>' . $registro->dia . '</td>';
        echo '<td>' . $registro->horario . '</td>';
        echo '<td><a href="' . get_edit_user_link($registro->user_id) . '#historico_navegacao" target="_blank">' . $user_data->display_name . '</a></td>';
        echo '<td>' . $registro->titulo_pagina . '</td>';
        echo '<td><a href="' . $registro->url_pagina . '" target="_blank">' . $registro->url_pagina . '</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}


// Função de callback para download dos registros
function download_historico_navegacao_callback() {
    global $wpdb;
    $nome_tabela = $wpdb->prefix . 'historico_navegacao';

    $registros = $wpdb->get_results("SELECT * FROM $nome_tabela ORDER BY dia DESC, horario DESC");

    $csv_data = array();
    
    $csv_data[] = array(
        'Dia',
        'Horário',
        'Usuário',
        'Título da Página',
        'URL da Página',
    );

    // Cria as linhas no formato CSV
    foreach ($registros as $registro) {
        $user_data = get_userdata($registro->user_id);
        $csv_data[] = array(
            $registro->dia,
            $registro->horario,
            $user_data->display_name,
            $registro->titulo_pagina,
            $registro->url_pagina
        );
    }

    // Gera o arquivo CSV
    $file_path = wp_upload_dir()['basedir'] . '/historico_navegacao.csv';
    $file = fopen($file_path, 'w');
    foreach ($csv_data as $row) {
        fputcsv($file, $row);
    }
    fclose($file);

    // Força o download do arquivo
    if (file_exists($file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename=' . basename($file_path));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }
}

// Registra a rota de download dos registros
add_action('wp_ajax_download_historico_navegacao', 'download_historico_navegacao_callback');
add_action('wp_ajax_nopriv_download_historico_navegacao', 'download_historico_navegacao_callback');



function adicionar_pagina_historico_navegacao() {
    add_menu_page(
        'Histórico de Navegação',
        'Histórico de Navegação',
        'read',
        'historico-navegacao',
        'exibir_historico_navegacao_geral',
        'dashicons-clock'
    );
}
add_action('admin_menu', 'adicionar_pagina_historico_navegacao');
