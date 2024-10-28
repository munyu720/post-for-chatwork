<?php
/*
Plugin Name: Post to Chatwork
Plugin URI: https://wordpress.org/plugins/post-for-chatwork/
Description: WordPressの投稿をチャットワークへ通知するPlugin
Version: 0.1.9
Author: KARIYA
Author URI: https://www.n.kariya01.com/
License: 
License URI: 
*/

add_action( 'transition_post_status', 'post_send_cw_message', 1, 3 );

add_action( 'admin_menu', 'post_send_cw_admin_menu');

function post_send_cw_message( $new_status, $old_status, $post ){
    // 新しいステータスが公開だったら
    if( $new_status == 'publish' ){
        if(!get_option('post_cw_api_token') || !get_option('post_cw_roomid'))return;
        switch($old_status){
            case 'draft':
            case 'pending':
            case 'auto-draft':
            case 'future':
                $expert_num = get_option('post_send_cwr_expert');
                $send_title = esc_html( $post->post_title );
                $send_content = get_the_custom_excerpt( apply_filters('the_content', $post->post_content ) , $expert_num );
                $type = esc_html( get_post_type_object( get_post_type($post) )->labels->name );

                $body = get_option('post_send_cwr_messege'). '[info][title]'.$send_title.'　投稿元：('.$type. ')  ' . get_permalink($post->ID) . '[/title]' . $send_content . '[/info]';
                $roomid = get_option('post_cw_roomid');
                $key = get_option('post_cw_api_token');
                $url = 'https://api.chatwork.com/v2/rooms/'.$roomid.'/messages';
                $data = array(
                    'body' => $body
                );
                $headers = array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'X-ChatWorkToken: '.$key
                );
                $options = array('http' => array(
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'header' => implode("\r\n", $headers),
                ));
                $contents = file_get_contents($url, false, stream_context_create($options));
                break;
            case 'private':
                break;
            case 'publish':
                break;
        }
    }
}
function post_send_cw_admin_menu(){
    add_options_page('ChatWork連携設定', 'ChatWork連携設定', 'administrator', __FILE__, 'post_send_cw_admin_opt_page');
    add_action( 'admin_init', 'register_post_send_cwr_settings' );
}
function register_post_send_cwr_settings() {
  register_setting( 'post_send_cwr-settings-group', 'post_cw_api_token' );
  register_setting( 'post_send_cwr-settings-group', 'post_cw_roomid' );
  register_setting( 'post_send_cwr-settings-group', 'post_send_cwr_messege' );
  register_setting( 'post_send_cwr-settings-group', 'post_send_cwr_expert' );
}
function post_send_cw_admin_opt_page(){
    ?>
<div class="wrap">
        <div id="icon-options-general" class="icon32"><br></div>
        <h2>ChatWork通知オプション</h2>
        <form method="post" action="options.php">
            <?php wp_nonce_field('update-options'); ?>
            <?php 
            settings_fields( 'post_send_cwr-settings-group' );
            do_settings_sections( 'post_send_cwr-settings-group' );
            ?>
            <p>指定した投稿に記事が投稿されるとChatWorkの指定のチャットルームに送信されます。</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="api_token">ChatWork APIトークン</label>
                    </th>
                    <td>
                        <input id="api_token" type="text" class="regular-text ltr" name="post_cw_api_token" value="<?php echo get_option('post_cw_api_token'); ?>" />
                        <p class="description">本プラグインの動作にはChatWork社のAPIトークンが必要になります。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="roomid">投稿先のチャットルームのルームID</label>
                    </th>
                    <td>
                        <input id="roomid" type="text" class="regular-text ltr" name="post_cw_roomid" value="<?php echo get_option('post_cw_roomid'); ?>" />
                        <p class="description">ルームIDはChatWorkでルームを選択した際に、ブラウザのアドレス欄に表示される右記のXXXXXXの数字を入力してください。（https://www.chatwork.com/#!ridXXXXXX）</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="post_send_cwr_messege">通知メッセージ</label>
                    </th>
                    <td>
                        <input id="messege" type="text" class="regular-text ltr" name="post_send_cwr_messege" value="<?php echo get_option('post_send_cwr_messege'); ?>" />
                        <p class="description">通知の前に入るメッセージです。</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="post_send_cwr_expert">抜粋の文字数</label>
                    </th>
                    <td>
                        <input id="expert" type="text" class="regular-text ltr" name="post_send_cwr_expert" value="<?php echo get_option('post_send_cwr_expert'); ?>" />
                        <p class="description">通知する本文の抜粋の文字数です。</p>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="page_options" value="post_cw_api_token,post_cw_roomid" />
            <p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
        </form>
</div>
</div>
<?php
}

/**
 * カスタム投稿の公開時に処理を追加する関数
 * 　transition_post_status　フックを使用することで不要になりました。
 * @return void
 */
function add_wpbs_save_post_hooks() {
    // デフォルト以外で、show_uiがtrue（管理画面が有効）となっている、追加されたカスタム投稿タイプを取得
    $additional_post_types = get_post_types( array( '_builtin' => false, 'show_ui' => true ) );
    foreach ( $additional_post_types as $post_type ) {
        // 追加されたカスタム投稿ごとにフックを追加
        add_action( 'publish_' . $post_type, 'post_send_cw_message', 1 );
    }
}

//本文抜粋を取得する関数
//使用方法：http://nelog.jp/get_the_custom_excerpt
function get_the_custom_excerpt($content, $length) {
    $length = ($length ? $length : 70);//デフォルトの長さを指定する
    $content =  preg_replace('/<!--more-->.+/is',"",$content); //moreタグ以降削除
    $content =  strip_shortcodes($content);//ショートコード削除
    $content =  strip_tags($content);//タグの除去
    $content =  str_replace("&nbsp;","",$content);//特殊文字の削除（今回はスペースのみ）
    $content =  mb_substr($content,0,$length);//文字列を指定した長さで切り取る
    return $content;
}
