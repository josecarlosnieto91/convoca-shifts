<?php
/**
 * Generador de PDF para Certificados de Turnos.
 *
 * @package Convoca\Shifts
 */

namespace Convoca\Shifts;

if (!defined('ABSPATH')) {
    exit;
}

class PDF_Certificado
{
    /**
     * Genera el certificado para un turno y devuelve el ID del documento.
     */
    public static function generar(int $post_id): ?int
    {
        if (!class_exists('\\Convoca\\Core\\BDV_Signature')) {
            error_log('Biodevas Turnos: BDV_Signature class not found.');
            return null;
        }

        $turno = get_post($post_id);
        if (!$turno || $turno->post_type !== 'centro_turno') {
            return null;
        }

        // Only generate for "realizado" status
        $estado_real = get_post_meta($post_id, '_estado_real', true);
        if ($estado_real !== 'realizado') {
            return null;
        }

        $user_id = (int) get_post_meta($post_id, '_id_responsable', true);
        if ($user_id <= 0) {
            return null;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        // Check if certificate already exists
        $existing = get_posts([
            'post_type' => 'bdv_documento',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_bdv_usuario_id',
                    'value' => $user_id
                ],
                [
                    'key' => '_bdv_turno_id',
                    'value' => $post_id
                ],
                [
                    'key' => '_bdv_tipo_documento',
                    'value' => 'certificado'
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        $signature = new \Convoca\Core\BDV_Signature();

        $nombre = $user->first_name ?: $user->display_name;
        $dni = get_user_meta($user_id, '_cst_dni', true) ?: (get_user_meta($user_id, '_bdv_dni', true) ?: 'N/A');
        
        $fecha_turno = wp_date('d/m/Y', get_post_timestamp($turno));
        $actividad = 'Turno en Centro Social: ' . $turno->post_title;

        $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'Desconocida';
        $timestamp = time();

        $content_for_hash = $user_id . $post_id . 'certificado' . $timestamp;
        
        $templates = get_option('bdv_pdf_templates', []);
        $template_html = isset($templates['certificado']) ? $templates['certificado']['content'] : '<h1>Certificado</h1><p>Nombre: {{nombre}}</p><p>DNI: {{dni}}</p><p>Actividad: {{actividad}}</p><p>Fecha: {{fecha}}</p>';
        
        $stamp_html = $signature->get_acceptance_stamp_html('Asociación Biodevas', $ip, $timestamp, $content_for_hash);
        
        if (strpos($template_html, '<!-- FIRMA DIGITAL SERÁ AÑADIDA POR LA CLASE BDV_Signature -->') !== false) {
            $template_html = str_replace('<!-- FIRMA DIGITAL SERÁ AÑADIDA POR LA CLASE BDV_Signature -->', $stamp_html, $template_html);
        } else {
            $template_html .= $stamp_html;
        }

        $data = [
            'nombre' => $nombre,
            'dni' => $dni,
            'actividad' => $actividad,
            'fecha' => $fecha_turno
        ];

        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/biodevas-documentos';
        
        $hash = $signature->create_hash($content_for_hash, $ip, $timestamp);
        $filename = 'certificado-turno-' . $post_id . '-user-' . $user_id . '-' . substr($hash, 0, 8) . '.pdf';
        $filepath = $target_dir . '/' . $filename;
        $fileurl = $upload_dir['baseurl'] . '/biodevas-documentos/' . $filename;

        $generated_path = $signature->generate_pdf($template_html, $data, $filepath);

        if (!$generated_path) {
            return null;
        }

        // Use the potentially sanitized path
        $filepath = $generated_path;
        $fileurl = $upload_dir['baseurl'] . '/biodevas-documentos/' . basename($filepath);

        $doc_id = wp_insert_post([
            'post_type' => 'bdv_documento',
            'post_title' => 'Certificado Turno ' . $post_id . ' - ' . $nombre,
            'post_status' => 'publish',
            'post_author' => 1
        ]);

        if (!is_wp_error($doc_id)) {
            update_post_meta($doc_id, '_bdv_usuario_id', $user_id);
            update_post_meta($doc_id, '_bdv_turno_id', $post_id);
            update_post_meta($doc_id, '_bdv_tipo_documento', 'certificado');
            update_post_meta($doc_id, '_bdv_hash', $hash);
            update_post_meta($doc_id, '_bdv_documento_url', rest_url('biodevas/v1/documentos/' . $doc_id));
            update_post_meta($doc_id, '_bdv_documento_path', $filepath);
            return $doc_id;
        }

        return null;
    }
}
