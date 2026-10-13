<?php
/**
 * Gọi OpenAI: văn bản (Responses API cho GPT-5/6, Chat Completions cho model cũ) và ảnh.
 */

defined( 'ABSPATH' ) || exit;

class ABCGS_OpenAI {

	const SYSTEM = 'You are a strict SEO content writer. Follow instructions exactly. Return only HTML without code fences.';

	public static function uses_responses_api( $model ) {
		return (bool) preg_match( '/^gpt-(5|6)(?:[.\-]|$)/i', (string) $model );
	}

	/**
	 * @return string|WP_Error
	 */
	public static function text( $api_key, $model, $prompt ) {
		if ( self::uses_responses_api( $model ) ) {
			$payload = array(
				'model'        => $model,
				'instructions' => self::SYSTEM,
				'input'        => $prompt,
				'reasoning'    => array( 'effort' => 'low' ),
			);
			$data = self::post( $api_key, 'responses', $payload, 120 );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$parts = array();
			foreach ( (array) ( $data['output'] ?? array() ) as $item ) {
				if ( 'message' !== ( $item['type'] ?? '' ) ) {
					continue;
				}
				foreach ( (array) ( $item['content'] ?? array() ) as $part ) {
					if ( 'output_text' === ( $part['type'] ?? '' ) && isset( $part['text'] ) ) {
						$parts[] = $part['text'];
					}
				}
			}
			$text = trim( implode( "\n", $parts ) );
			if ( '' === $text ) {
				return new WP_Error( 'abcgs_empty', 'Responses API không trả về văn bản (status: ' . sanitize_text_field( (string) ( $data['status'] ?? 'unknown' ) ) . ').' );
			}
			return $text;
		}

		$payload = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => self::SYSTEM,
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => 0.7,
		);
		$data = self::post( $api_key, 'chat/completions', $payload, 120 );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$text = trim( (string) ( $data['choices'][0]['message']['content'] ?? '' ) );
		if ( '' === $text ) {
			return new WP_Error( 'abcgs_empty', 'API không trả về nội dung.' );
		}
		return $text;
	}

	/**
	 * Tạo ảnh, trả về nội dung nhị phân.
	 *
	 * @return string|WP_Error
	 */
	public static function image( $api_key, array $settings, $prompt ) {
		$format  = ABCGS_Settings::pick( $settings['image_format'] ?? '', ABCGS_Settings::image_formats(), 'webp' );
		$payload = array(
			'model'         => $settings['image_model'] ? $settings['image_model'] : 'gpt-image-1',
			'prompt'        => $prompt,
			'size'          => ABCGS_Settings::pick( $settings['image_size'] ?? '', ABCGS_Settings::image_sizes(), '1536x1024' ),
			'quality'       => ABCGS_Settings::pick( $settings['image_quality'] ?? '', ABCGS_Settings::image_qualities(), 'medium' ),
			'n'             => 1,
			'output_format' => $format,
		);
		if ( in_array( $format, array( 'webp', 'jpeg' ), true ) ) {
			$payload['output_compression'] = 82;
		}

		$data = self::post( $api_key, 'images/generations', $payload, 180 );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$item  = $data['data'][0] ?? array();
		$bytes = false;
		if ( ! empty( $item['b64_json'] ) ) {
			$bytes = base64_decode( $item['b64_json'], true );
		} elseif ( ! empty( $item['url'] ) ) {
			// wp_safe_remote_get chặn IP nội bộ/localhost.
			$img = wp_safe_remote_get( esc_url_raw( $item['url'] ), array( 'timeout' => 120 ) );
			if ( ! is_wp_error( $img ) && 200 === wp_remote_retrieve_response_code( $img ) ) {
				$bytes = wp_remote_retrieve_body( $img );
			}
		}
		if ( ! $bytes || strlen( $bytes ) < 1000 ) {
			return new WP_Error( 'abcgs_image_empty', 'API không trả về ảnh hợp lệ.' );
		}
		return $bytes;
	}

	/**
	 * @return array|WP_Error
	 */
	private static function post( $api_key, $endpoint, array $payload, $timeout ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/' . $endpoint,
			array(
				'timeout'     => $timeout,
				'sslverify'   => true,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'abcgs_http', 'Không kết nối được OpenAI: ' . $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			// Chỉ lấy thông điệp lỗi, không lưu toàn bộ phản hồi.
			$msg = is_array( $data ) ? (string) ( $data['error']['message'] ?? '' ) : '';
			return new WP_Error( 'abcgs_api', 'OpenAI HTTP ' . $code . ( $msg ? ': ' . mb_substr( sanitize_text_field( $msg ), 0, 300 ) : '' ) );
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'abcgs_json', 'Phản hồi JSON không hợp lệ.' );
		}
		return $data;
	}
}
