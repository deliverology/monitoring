<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
	
	class Auth extends CI_Controller
	{
		function __construct()
		{
			parent::__construct();
			
			if(version_compare(CI_VERSION,'2.1.0','<')){
				$this->load->library('security');
			}
			$this->lang->load('tank_auth');
			backButtonHandle();
		}
		
		function index()
		{
			if ($message = $this->session->flashdata('message')) {
				$this->load->view('auth/general_message', array('message' => $message));
				} else {
				redirect('/auth/login/');
			}
		}
		
		/**
		* Login user on the site
		*
		* @return void
		*/
		function login()
		{
			if ($this->tank_auth->is_logged_in()) {									// logged in
				redirect('monitor');
				
				} elseif ($this->tank_auth->is_logged_in(FALSE)) {						// logged in, not activated
				redirect('/auth/send_again/');
				
				} else {
				$data['login_by_username'] = ($this->config->item('login_by_username', 'tank_auth') AND
				$this->config->item('use_username', 'tank_auth'));
				$data['login_by_email'] = $this->config->item('login_by_email', 'tank_auth');
				
				$this->form_validation->set_rules('login', 'Login', 'trim|required|xss_clean');
				$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
				$this->form_validation->set_rules('remember', 'Remember me', 'integer');
				
				// Get login for counting attempts to login
				if ($this->config->item('login_count_attempts', 'tank_auth') AND
				($login = $this->input->post('login'))) {
					$login = $this->security->xss_clean($login);
					} else {
					$login = '';
				}
				
				$data['use_recaptcha'] = $this->config->item('use_recaptcha', 'tank_auth');
				if ($this->tank_auth->is_max_login_attempts_exceeded($login)) {
					if ($data['use_recaptcha'])
					$this->form_validation->set_rules('recaptcha_response_field', 'Confirmation Code', 'trim|xss_clean|required|callback__check_recaptcha');
					else
					$this->form_validation->set_rules('captcha', 'Confirmation Code', 'trim|xss_clean|required|callback__check_captcha');
				}
				$data['errors'] = array();
				
				if ($this->form_validation->run()) {								// validation ok
					if ($this->tank_auth->login(
					$this->form_validation->set_value('login'),
					$this->form_validation->set_value('password'),
					$this->form_validation->set_value('remember'),
					$data['login_by_username'],
					$data['login_by_email'])) {								// success
						redirect('');
						
						} else {
						$errors = $this->tank_auth->get_error_message();
						if (isset($errors['banned'])) {								// banned user
							$this->_show_message($this->lang->line('auth_message_banned').' '.$errors['banned']);
							
							} elseif (isset($errors['not_activated'])) {				// not activated user
							redirect('/auth/send_again/');
							
							} else {													// fail
							foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
						}
					}
				}
				$data['show_captcha'] = FALSE;
				if ($this->tank_auth->is_max_login_attempts_exceeded($login)) {
					$data['show_captcha'] = TRUE;
					if ($data['use_recaptcha']) {
						$data['recaptcha_html'] = $this->_create_recaptcha();
						} else {
						$data['captcha_html'] = $this->_create_captcha();
					}
				}
				$this->load->view('auth/login_form', $data);
			}
		}
		
		/**
		* Logout user
		*
		* @return void
		*/
		function logout()
		{
			$this->tank_auth->logout();
			redirect('auth/login');
			//$this->_show_message($this->lang->line('auth_message_logged_out'));
		}
		
		/**
		* Register user on the site
		*
		* @return void
		*/
		function register()
		{
				if (!$this->input->is_ajax_request()) redirect('auth/login');
				if (!$this->config->item('allow_registration', 'tank_auth')) {	// registration is off
				$this->_show_message($this->lang->line('auth_message_registration_disabled'));
				
				} else {
				$captcha_registration	= $this->config->item('captcha_registration', 'tank_auth');
				$use_recaptcha			= $this->config->item('use_recaptcha', 'tank_auth');
				$use_username = $this->config->item('use_username', 'tank_auth');
				$data['use_username'] = $use_username;
				$data['captcha_registration'] = $captcha_registration;
				$data['use_recaptcha'] = $use_recaptcha;
				$this->load->model('m_setting');
				$data['option_roles'] = $this->m_setting->get_roles();
				if($this->input->post('tambah')){
					
					if ($use_username) {
					$this->form_validation->set_rules('username', 'Username', 'trim|required|xss_clean|min_length['.$this->config->item('username_min_length', 'tank_auth').']|max_length['.$this->config->item('username_max_length', 'tank_auth').']|alpha_dash');
					}
					$this->form_validation->set_rules('role', 'Role', 'required|xss_clean');
					$this->form_validation->set_rules('instansi', 'Instansi / Unit Kerja', 'required|xss_clean');
					$this->form_validation->set_rules('nama', 'Nama', 'required|xss_clean');
					$this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
					$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
					$this->form_validation->set_rules('confirm_password', 'Confirm Password', 'trim|required|xss_clean|matches[password]');
					
					if ($captcha_registration) {
						if ($use_recaptcha) {
							$this->form_validation->set_rules('recaptcha_response_field', 'Confirmation Code', 'trim|xss_clean|required|callback__check_recaptcha');
							} else {
							$this->form_validation->set_rules('captcha', 'Confirmation Code', 'trim|xss_clean|required|callback__check_captcha');
						}
					}
					$data['errors'] = array();
					
					$email_activation = $this->config->item('email_activation', 'tank_auth');
					
						if ($this->form_validation->run()) {
							if ($this->tank_auth->check_username($this->form_validation->set_value('username')) == 1){
							$cek_username = 1;
						} else {
							$cek_username = 0;
							$data['errors_username'] = "Username tersebut sudah terdaftar. Mohon gunakan username lain.";
						}
						if($this->tank_auth->check_email($this->form_validation->set_value('email')) == 1){
							$cek_email = 1;
						} else {
							$cek_email = 0;
							$data['errors_email'] = "Email tersebut sudah terdaftar. Mohon gunakan alamat email lain.";
						}
						if($cek_username == 1 && $cek_email == 1){
						// validation ok
							if (!is_null($data = $this->tank_auth->create_user(
							$use_username ? $this->form_validation->set_value('username') : '',
							$this->form_validation->set_value('email'),
							$this->form_validation->set_value('password'),
							$email_activation,
							$this->form_validation->set_value('nama'),
							$this->form_validation->set_value('role'),
							$this->input->post('jabatan'),
							$this->input->post('hp'),
							$this->form_validation->set_value('instansi')))) {									// success
								
							$data['site_name'] = $this->config->item('website_name', 'tank_auth');
							
							if ($email_activation) {									// send "activate" email
								$data['activation_period'] = $this->config->item('email_activation_expire', 'tank_auth') / 3600;
								
								//$this->_send_email('activate', $data['email'], $data);
								
								unset($data['password']); // Clear password (just for any case)
								
								//$this->_show_message($this->lang->line('auth_message_registration_completed_1'));
								
								} else {
								if ($this->config->item('email_account_details', 'tank_auth')) {	// send "welcome" email
									
									//$this->_send_email('welcome', $data['email'], $data);
								}
								unset($data['password']); // Clear password (just for any case)
								
								//$this->_show_message($this->lang->line('auth_message_registration_completed_2').' '.anchor('/auth/login/', 'Login'));
								}
							echo 'success';
							}
						}
						else {
							$errors = $this->tank_auth->get_error_message();
							foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
							if ($captcha_registration) {
								if ($use_recaptcha) {
									$data['recaptcha_html'] = $this->_create_recaptcha();
									} else {
									$data['captcha_html'] = $this->_create_captcha();
								}
							}	
							$this->load->view('app/setting/form/modal_user_add',$data);
							}
					}
					else {
						$errors = $this->tank_auth->get_error_message();
						foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
						if ($captcha_registration) {
							if ($use_recaptcha) {
								$data['recaptcha_html'] = $this->_create_recaptcha();
								} else {
								$data['captcha_html'] = $this->_create_captcha();
							}
						}	
						$this->load->view('app/setting/form/modal_user_add',$data);
					}
				}
				else {
					if ($captcha_registration) {
						if ($use_recaptcha) {
							$data['recaptcha_html'] = $this->_create_recaptcha();
							} else {
							$data['captcha_html'] = $this->_create_captcha();
						}
					}
					$this->load->view('app/setting/form/modal_user_add',$data);
				}
			}
		}
		
		/**
		* Send activation email again, to the same or new email address
		*
		* @return void
		*/
		function send_again()
		{
			if (!$this->tank_auth->is_logged_in(FALSE)) {							// not logged in or activated
				redirect('/auth/login/');
				
				} else {
				$this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
				
				$data['errors'] = array();
				
				if ($this->form_validation->run()) {								// validation ok
					if (!is_null($data = $this->tank_auth->change_email(
					$this->form_validation->set_value('email')))) {			// success
						
						$data['site_name']	= $this->config->item('website_name', 'tank_auth');
						$data['activation_period'] = $this->config->item('email_activation_expire', 'tank_auth') / 3600;
						
						$this->_send_email('activate', $data['email'], $data);
						
						$this->_show_message(sprintf($this->lang->line('auth_message_activation_email_sent'), $data['email']));
						
						} else {
						$errors = $this->tank_auth->get_error_message();
						foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
					}
				}
				$this->load->view('auth/send_again_form', $data);
			}
		}
		
		/**
		* Activate user account.
		* User is verified by user_id and authentication code in the URL.
		* Can be called by clicking on link in mail.
		*
		* @return void
		*/
		function activate()
		{
			$user_id		= $this->uri->segment(3);
			$new_email_key	= $this->uri->segment(4);
			
			// Activate user
			if ($this->tank_auth->activate_user($user_id, $new_email_key)) {		// success
				$this->tank_auth->logout();
				$this->_show_message($this->lang->line('auth_message_activation_completed').' '.anchor('/auth/login/', 'Login'));
				
				} else {																// fail
				$this->_show_message($this->lang->line('auth_message_activation_failed'));
			}
		}
		
		/**
		* Generate reset code (to change password) and send it to user
		*
		* @return void
		*/
		function forgot_password()
		{
			if (!$this->input->is_ajax_request()) redirect('auth/login');
			if ($this->tank_auth->is_logged_in()) {									// logged in
				redirect('');
				
				} elseif ($this->tank_auth->is_logged_in(FALSE)) {						// logged in, not activated
				redirect('/auth/send_again/');
				
				} else {
				$this->form_validation->set_rules('login', 'Email or login', 'trim|required|xss_clean');
				
				$data['errors'] = array();
				if ( $this->form_validation->run() === FALSE )
				{
					$v =validation_errors();
					$str= "<div class='alert alert-danger'>
							<button class='close' data-close='alert'></button>
							<span>
							$v
							</span>
							</div>";
					echo json_encode(array('st'=>0,'msg'=>$str));
				}
				else 
				{
					if ($this->form_validation->run()) {								// validation ok
						if (!is_null($data = $this->tank_auth->forgot_password(
						$this->form_validation->set_value('login')))) {
							
							$data['site_name'] = $this->config->item('website_name', 'tank_auth');
							
							// Send email with password activation link
							$this->_send_email('forgot_password', $data['email'], $data);
							
							$str = $this->lang->line('auth_message_new_password_sent');
							echo json_encode(array('st'=>1,'msg'=>$str));
							} else {
							$errors = $this->tank_auth->get_error_message();
							foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
							$v=$data["errors"][$k];
							$str= "<div class='alert alert-danger'>
							<button class='close' data-close='alert'></button>
							<span>
							$v
							</span>
							</div>";
							echo json_encode(array('st'=>0,'msg'=>$str));
						}
						
					}
				}
				
			}
		}
		
		/**
		* Replace user password (forgotten) with a new one (set by user).
		* User is verified by user_id and authentication code in the URL.
		* Can be called by clicking on link in mail.
		*
		* @return void
		*/
		function reset_password()
		{
			if (!$this->input->is_ajax_request()) redirect('auth/login');
			$user_id		= $this->uri->segment(3);
			$new_pass_key	= $this->uri->segment(4);
			
			$this->form_validation->set_rules('new_password', 'New Password', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
			$this->form_validation->set_rules('confirm_new_password', 'Confirm new Password', 'trim|required|xss_clean|matches[new_password]');
			
			$data['errors'] = array();
			
			if ($this->form_validation->run()) {								// validation ok
				if (!is_null($data = $this->tank_auth->reset_password(
				$user_id, $new_pass_key,
				$this->form_validation->set_value('new_password')))) {	// success
					
					$data['site_name'] = $this->config->item('website_name', 'tank_auth');
					
					// Send email with new password
					$this->_send_email('reset_password', $data['email'], $data);
					
					$this->_show_message($this->lang->line('auth_message_new_password_activated').' '.anchor('/auth/login/', 'Login'));
					
					} else {														// fail
					$this->_show_message($this->lang->line('auth_message_new_password_failed'));
				}
				} else {
				// Try to activate user by password key (if not activated yet)
				if ($this->config->item('email_activation', 'tank_auth')) {
					$this->tank_auth->activate_user($user_id, $new_pass_key, FALSE);
				}
				
				if (!$this->tank_auth->can_reset_password($user_id, $new_pass_key)) {
					$this->_show_message($this->lang->line('auth_message_new_password_failed'));
				}
			}
			$this->load->view('auth/reset_password_form', $data);
		}
		
		/**
		* Change user password
		*
		* @return void
		*/
		function change_password()
		{
			if (!$this->input->is_ajax_request()) redirect('auth/login');
			if (!$this->tank_auth->is_logged_in()) {								// not logged in or not activated
				redirect('/auth/login/');
				
				} else {
				$this->form_validation->set_rules('old_password', 'Password Lama', 'trim|required|xss_clean');
				$this->form_validation->set_rules('new_password', 'Password Baru', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
				$this->form_validation->set_rules('confirm_new_password', 'Konfirmasi Password Baru', 'trim|required|xss_clean|matches[new_password]');
				
				$data['errors'] = array();
				
				if ($this->form_validation->run()) {								// validation ok
					if ($this->tank_auth->change_password(
					$this->form_validation->set_value('old_password'),
					$this->form_validation->set_value('new_password'))) {	// success
						//$this->_show_message($this->lang->line('auth_message_password_changed'));
						echo "success";
						} else {														// fail
						$errors = $this->tank_auth->get_error_message();
						foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
						$this->load->view('app/profile/form/modal_change_password', $data);
					}
				}
				else $this->load->view('app/profile/form/modal_change_password', $data);
			}
		}
		
		/**
		* Change user email
		*
		* @return void
		*/
		function change_email()
		{
			if (!$this->input->is_ajax_request()) redirect('auth/login');
			if (!$this->tank_auth->is_logged_in()) {								// not logged in or not activated
				redirect('/auth/login/');
				
				} else {
				$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
				$this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
				
				$data['errors'] = array();
				
				if ($this->form_validation->run()) {								// validation ok
					if (!is_null($data = $this->tank_auth->set_new_email(
					$this->form_validation->set_value('email'),
					$this->form_validation->set_value('password')))) {			// success
						
						$data['site_name'] = $this->config->item('website_name', 'tank_auth');
						
						// Send email with new email address and its activation link
						//$this->_send_email('change_email', $data['new_email'], $data);
						
						//$this->_show_message(sprintf($this->lang->line('auth_message_new_email_sent'), $data['new_email']));
						
						} else {
						$errors = $this->tank_auth->get_error_message();
						foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
					}
				}
				$this->load->view('auth/change_email_form', $data);
			}
		}
		
		/**
		* Replace user email with a new one.
		* User is verified by user_id and authentication code in the URL.
		* Can be called by clicking on link in mail.
		*
		* @return void
		*/
		function reset_email()
		{
			if (!$this->input->is_ajax_request()) redirect('auth/login');
			$user_id		= $this->uri->segment(3);
			$new_email_key	= $this->uri->segment(4);
			
			// Reset email
			if ($this->tank_auth->activate_new_email($user_id, $new_email_key)) {	// success
				$this->tank_auth->logout();
				$this->_show_message($this->lang->line('auth_message_new_email_activated').' '.anchor('/auth/login/', 'Login'));
				
				} else {																// fail
				$this->_show_message($this->lang->line('auth_message_new_email_failed'));
			}
		}
		
		/**
		* Delete user from the site (only when user is logged in)
		*
		* @return void
		*/
		function unregister()
		{
			if (!$this->input->is_ajax_request()) redirect('auth/login');
			if (!$this->tank_auth->is_logged_in()) {								// not logged in or not activated
				redirect('/auth/login/');
				
				} else {
				$this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
				
				$data['errors'] = array();
				
				if ($this->form_validation->run()) {								// validation ok
					if ($this->tank_auth->delete_user(
					$this->form_validation->set_value('password'))) {		// success
						$this->_show_message($this->lang->line('auth_message_unregistered'));
						
						} else {														// fail
						$errors = $this->tank_auth->get_error_message();
						foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
					}
				}
				$this->load->view('auth/unregister_form', $data);
			}
		}
		
		/**
		* Show info message
		*
		* @param	string
		* @return	void
		*/
		function _show_message($message)
		{
			$this->session->set_flashdata('message', $message);
			redirect('/auth/');
		}
		
		/**
		* Send email message of given type (activate, forgot_password, etc.)
		*
		* @param	string
		* @param	string
		* @param	array
		* @return	void
		*/
		function _send_email($type, $email, &$data)
		{
			$this->load->library('email');
			$this->email->set_newline("\r\n");
			$config = Array(
			'protocol' => 'smtp',
			//'protocol' => 'sendmail',
			'smtp_host' => 'aspmx.l.google.com',
			'smtp_port' => 25,
			'smtp_user' => 'keraktelor89@gmail.com',
			'smtp_pass' => 'kaliurang',
			'mailtype'  => 'html'
			);
	        $this->email->initialize($config); 
			$this->email->from($this->config->item('webmaster_email', 'tank_auth'), $this->config->item('website_name', 'tank_auth'));
			$this->email->reply_to($this->config->item('webmaster_email', 'tank_auth'), $this->config->item('website_name', 'tank_auth'));
			$this->email->to($email);
			$this->email->subject(sprintf($this->lang->line('auth_subject_'.$type), $this->config->item('website_name', 'tank_auth')));
			$this->email->message($this->load->view('email/'.$type.'-html', $data, TRUE));
			$this->email->set_alt_message($this->load->view('email/'.$type.'-txt', $data, TRUE));
			if ( ! $this->email->send())
			{
				show_error($this->email->print_debugger());
			}  
		}
		
		/**
		* Create CAPTCHA image to verify user as a human
		*
		* @return	string
		*/
		function _create_captcha()
		{
			$this->load->helper('captcha');
			
			$cap = create_captcha(array(
			'img_path'		=> './'.$this->config->item('captcha_path', 'tank_auth'),
			'img_url'		=> base_url().$this->config->item('captcha_path', 'tank_auth'),
			'font_path'		=> './'.$this->config->item('captcha_fonts_path', 'tank_auth'),
			'font_size'		=> $this->config->item('captcha_font_size', 'tank_auth'),
			'img_width'		=> $this->config->item('captcha_width', 'tank_auth'),
			'img_height'	=> $this->config->item('captcha_height', 'tank_auth'),
			'show_grid'		=> $this->config->item('captcha_grid', 'tank_auth'),
			'expiration'	=> $this->config->item('captcha_expire', 'tank_auth'),
			));
			
			// Save captcha params in session
			$this->session->set_flashdata(array(
			'captcha_word' => $cap['word'],
			'captcha_time' => $cap['time'],
			));
			
			return $cap['image'];
		}
		
		/**
		* Callback function. Check if CAPTCHA test is passed.
		*
		* @param	string
		* @return	bool
		*/
		function _check_captcha($code)
		{
			$time = $this->session->flashdata('captcha_time');
			$word = $this->session->flashdata('captcha_word');
			
			list($usec, $sec) = explode(" ", microtime());
			$now = ((float)$usec + (float)$sec);
			
			if ($now - $time > $this->config->item('captcha_expire', 'tank_auth')) {
				$this->form_validation->set_message('_check_captcha', $this->lang->line('auth_captcha_expired'));
				return FALSE;
				
			} elseif (($this->config->item('captcha_case_sensitive', 'tank_auth') AND
			$code != $word) OR
			strtolower($code) != strtolower($word)) {
				$this->form_validation->set_message('_check_captcha', $this->lang->line('auth_incorrect_captcha'));
				return FALSE;
			}
			return TRUE;
		}
		
		/**
		* Create reCAPTCHA JS and non-JS HTML to verify user as a human
		*
		* @return	string
		*/
		function _create_recaptcha()
		{
			$this->load->helper('recaptcha');
			
			// Add custom theme so we can get only image
			$options = "<script>var RecaptchaOptions = {theme: 'custom', custom_theme_widget: 'recaptcha_widget'};</script>\n";
			
			// Get reCAPTCHA JS and non-JS HTML
			$html = recaptcha_get_html($this->config->item('recaptcha_public_key', 'tank_auth'),NULL,$this->config->item('enable_ssl_recaptcha', 'tank_auth'));
			
			return $options.$html;
		}
		
		/**
		* Callback function. Check if reCAPTCHA test is passed.
		*
		* @return	bool
		*/
		function _check_recaptcha()
		{
			$this->load->helper('recaptcha');
			
			$resp = recaptcha_check_answer($this->config->item('recaptcha_private_key', 'tank_auth'),
			$_SERVER['REMOTE_ADDR'],
			$_POST['recaptcha_challenge_field'],
			$_POST['recaptcha_response_field']);
			
			if (!$resp->is_valid) {
				$this->form_validation->set_message('_check_recaptcha', $this->lang->line('auth_incorrect_captcha'));
				return FALSE;
			}
			return TRUE;
		}
		function tes(){
			echo $this->tank_auth->check_username('budi');
		}
		
	}
	
	/* End of file auth.php */
/* Location: ./application/controllers/auth.php */