<?php
	/**
	 * Comment Template.
	 *
	 * @todo Implement .this needs to be sorted out.
	 *
	 * Copyright (c) 2009 Carl Sutton ( dogmatic69 )
	 *
	 * Licensed under The MIT License
	 * Redistributions of files must retain the above copyright notice.
	 * @filesource
	 * @copyright Copyright (c) 2009 Carl Sutton ( dogmatic69 )
	 * @link http://infinitas-cms.org
	 * @package sort
	 * @subpackage sort.comments
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.5a
	 */

	class NewslettersController extends NewsletterAppController {
		public function beforeFilter() {
			parent::beforeFilter();
			//  @todo make sure function track is allowed by all
			//$this->Auth->allow('track');
			return true;
		}

		public function contact() {
			if(!empty($this->request->data)) {
				$body = '<p>A new email has been sent from your site. The details are below</p>';
				$body .= sprintf('<p>The sender: %s <%>s</p>', h($this->request->data['Newsletter']['name']), $this->request->data['Newsletter']['email']);
				$body .= sprintf('<p>IP address: %s</p>', $this->Auth->user('ip_address'));
				$body .= '<p>=====================================================</p>';
				$body .= htmlspecialchars($this->request->data['Newsletter']['query']);

				if(empty($this->request->data['Newsletter']['subject'])) {
					$subject = sprintf('New email from %s', Configure::read('Website.name'));
				}
				
				else {
					$subject = strip_tags($this->request->data['Newsletter']['subject']);
				}

				foreach(ClassRegistry::init('Users.User')->getAdmins() as $username => $email) {
					$this->Emailer->sendDirectMail(
						sprintf('%s <%s>', $username, $email),
						array(
							'subject' => $subject,
							'body' => $body
						)
					);
				}
				pr(ClassRegistry::init('Users.User')->getAdmins());
				exit;

				$this->notice(
					'Your message has been sent',
					array(
						'redirect' => '/'
					)
				);
			}

			$this->request->data['Newsletter']['name'] = $this->Auth->user('username');
			$this->request->data['Newsletter']['email'] = $this->Auth->user('email');
		}

		public function track($id) {
			Configure::write('debug', 0);
			$this->autoRender = false;
			$this->layout = 'ajax';

			if (!$id) {
				$this->log('no id for email tracking', 'newsletter');
				exit;
			}

			$this->Newsletter->id = $id;
			$views = $this->Newsletter->read('views');

			if (empty($views)) {
				$this->log('no newsletter found with id: ' . $id);
				exit;
			}

			if (!$this->Newsletter->saveField('views', $views['Newsletter']['views'] + 1)) {
				$this->log('could not save a view for id: ' . $id);
			}
			exit;
		}

		public function sendEmail() {
			$this->autoRender = false;
			$info = array_merge(
				array(
					'to' => array(),
					'cc' => array(),
					'bcc' => array(),
					'subject' => null,
					'html' => null,
					'text' => null
				),
				$this->request->params['named']['email']
			);
			var_dump($info);

			$this->Emailer->to  = $info['to'];
			$this->Emailer->bcc = $info['bcc'];
			$this->Emailer->subject = $info['subject'];
			$this->Emailer->template = 'blank';
			$this->set('info', $info);

		    $this->Emailer->delivery = 'smtp';
			
			var_dump($this->Emailer->send($info['html']));
			exit;
		}

		public function sendNewsletters() {
			$this->autoRender = false;
			$this->layout = 'ajax';
			Configure::write('debug', 0);

			$newsletters = $this->Newsletter->find(
				'all',
				array(
					'fields' => array(
						'Newsletter.id',
						'Newsletter.html',
						'Newsletter.text',
						'Newsletter.sends'
					),
					'conditions' => array(
						'Newsletter.sent' => 0,
						'Newsletter.active' => 1,
					),
					'contain' => array(
						'Template' => array(
							'fields' => array(
								'Template.header',
								'Template.footer',
							)
						),
						'User' => array(
							'fields' => array(
								'User.id',
								'User.email',
								'User.username'
							),
							'conditions' => array(
								'NewslettersUser.sent' => 0
							)
						)
					)
				)
			);

			foreach($newsletters as $newsletter) {
				if (empty($newsletter['User'])) {
					continue;
				}

				$html = $newsletter['Template']['header'] . $newsletter['Newsletter']['html'] . $newsletter['Template']['footer'];

				$search = array(
					'<br/>',
					'<br>',
					'</p><p>'
				);

				$text = strip_tags(
					str_replace($search,
						"\n\r",
						$newsletter['Template']['header'] . $newsletter['Newsletter']['html'] . $newsletter['Template']['footer']
					)
				);

				foreach($newsletter['User'] as $user) {
					$to = $user['email'];
					$name = $user['username'];
					//  @todo send the email here
					if (false) {
						$this->Newsletter->NewslettersUser->id = $user['NewslettersUser']['id'];
						if (!$this->Newsletter->NewslettersUser->saveField('sent', 1)) {
							$this->log('problem sending mail #' . $newsletter['Newsletter']['id'] . ' to user #' . $user['id'], 'newsletter');
						}

						$this->Newsletter->id = $newsletter['Newsletter']['id'];
						if (!$this->Newsletter->saveField('sends', $newsletter['Newsletter']['sends'] + 1)) {
							$this->log('problem counting send for mail #' . $newsletter['Newsletter']['id'], 'newsletter');
						}
					}
				}
			}
		}

		public function admin_dashboard() {
			$hasCampaign = $this->Newsletter->Campaign->find('count') >= 1;
			$hasTemplate = $this->Newsletter->Template->find('count') >= 1;
			$hasNewsletter = $this->Newsletter->find('count') >= 1;

			$this->set(compact('hasCampaign', 'hasTemplate', 'hasNewsletter'));
		}

		public function admin_report($id) {
			if (!$id) {
				$this->notice('invalid');
			}
		}

		public function admin_index() {
			$this->Paginator->settings = array(
				'fields' => array(
					'Newsletter.id',
					'Newsletter.campaign_id',
					'Newsletter.from',
					'Newsletter.reply_to',
					'Newsletter.subject',
					'Newsletter.active',
					'Newsletter.sent',
					'Newsletter.created',
					),
				'limit' => 20,
				'contain' => array(
					'Campaign' => array(
						'fields' => array(
							'Campaign.name'
						)
					)
				)
			);

			$newsletters = $this->Paginator->paginate('Newsletter', $this->Filter->filter);

			$filterOptions = $this->Filter->filterOptions;
			$filterOptions['fields'] = array(
				'subject',
				'html',
				'from',
				'reply_to'
			);

			$this->set(compact('newsletters', 'filterOptions'));
		}

		public function admin_add() {
			if ($this->request->isPost()) {	
				$campaignId = $this->request->data['Newsletter']['campaign_id'];
				
				$campaign = $this->Newsletter->Campaign->find('first', array(
					'fields' => array(
						'template_id'
						),
					'conditions' => array(
						'Campaign.id' => $campaignId
						)
					)
				);

				$this->request->data['Newsletter']['template_id'] = $campaign['Campaign']['template_id'];
			}
		
			parent::admin_add();

			$campaigns = $this->Newsletter->Campaign->find('list');
			if(empty($campaigns)) {
				$this->notice(
					__('Please create a campaign before creating a newsletter'),
					array(
						'level' => 'notice',
						'redirect' => array(
							'controller' => 'campaigns'
						)
					)
				);
			}

			$this->set(compact('campaigns'));
		}

		public function admin_view($id = null) {
			if (!$id && empty($this->request->data)) {
				$this->notice('invalid');
			}

			$newsletter = $this->Newsletter->read(null, $id);
			$templateId = $newsletter['Newsletter']['template_id'];
			$template = $this->Newsletter->Template->read(null, $templateId);

			if (!empty($this->request->data)) {
				$id = $this->request->data['Newsletter']['id'];

				$addresses = explode(',', $this->request->data['Newsletter']['email_addresses']);
				if (empty($addresses)) {
					$this->notice(
						__('Please input at least one email address for testing'),
						array(
							'level' => 'warning',
							'redirect' => true
						)
					);
				}
				
				$sent = 0;
				foreach($addresses as $address) {
					$email = new InfinitasEmail('gmail');
					$email->from(array($newsletter['Newsletter']['from'] => 'Infinitas'));
					$email->to($address);

					$email->subject(strip_tags($newsletter['Newsletter']['subject']));

					if ($email->send($template['Template']['header'] . $newsletter['Newsletter']['html'] . $template['Template']['footer'])) {
						$sent++;
					}
				} 
				
				$this->notice(sprintf(__('%s mails were sent'), $sent));
			}

			if (empty($this->request->data) && $id) {
				$this->request->data = $newsletter;
			}

			$this->set('newsletter', $this->Newsletter->read(null, $id));
		}

		public function admin_edit($id = null) {
			parent::admin_edit();

			$this->set('campaigns', $this->Newsletter->Campaign->find('list'));
		}

		public function admin_preview($id = null) {
			$this->layout = 'ajax';

			if (!$id) {
				$this->set('data', __('The template was not found'));
			}else {
				$newsletter = $this->Newsletter->find(
					'first',
					array(
						'fields' => array(
							'Newsletter.id',
							'Newsletter.html'
						),
						'conditions' => array(
							'Newsletter.id' => $id
						),
						'contain' => array(
							'Template' => array(
								'fields' => array(
									'Template.header',
									'Template.footer',
								)
							)
						)
					)
				);

				$this->set('data', $newsletter['Template']['header'] . $newsletter['Newsletter']['html'] . $newsletter['Template']['footer']);
				Configure::write('debug', 0);
			}
		}

		public function __massActionDelete($ids) {
			return $this->MassAction->delete($this->__canDelete($ids));
		}

		private function __canDelete($ids) {
			$newsletters = $this->Newsletter->find(
				'list',
				array(
					'fields' => array(
						'Newsletter.id',
						'Newsletter.id'
					),
					'conditions' => array(
						'Newsletter.sent' => 0, // only get mails that are not sent
						'Newsletter.sends > ' => 0, // get mails that have not sent anything.
						'Newsletter.id' => $ids
					)
				)
			);

			if (empty($newsletters)) {
				$this->notice(
					__('There are no newsletters to delete.'),
					array(
						'level' => 'warning',
						'redirect' => 'true'
					)
				);
			}
			return $newsletters;
		}

		public function admin_toggleSend($id = null) {
			if (!$id) {
				$this->notice('invalid');
			}

			$this->Newsletter->recursive = - 1;
			$sent = $this->Newsletter->read(array('id', 'sent', 'active'), $id);

			if (!isset($sent['Newsletter']['sent'])) {
				$this->notice(
					__('The newsletter was not found'),
					array(
						'level' => 'error',
						'redirect' => true
					)
				);
			}

			if ($sent['Newsletter']['sent']) {
				$this->notice(
					__('The newsletter has already been sent'),
					array(
						'level' => 'warning',
						'redirect' => true
					)
				);
			}

			if (!$sent['Newsletter']['active']) {
				$sent['Newsletter']['active'] = 1;

				if (!$this->Newsletter->save($sent)) {
					$this->notice(
						__('Could not activate the newsletter'),
						array(
							'level' => 'error',
							'redirect' => true
						)
					);
				}
			}

			$this->notice(
				__('Newsletter is now sending.'),
				array(
					'redirect' => true
				)
			);
		}

		public function admin_stopAll() {
			$runningNewsletters = $this->Newsletter->find(
				'list',
				array(
					'fields' => array(
						'Newsletter.id',
						'Newsletter.id'
					),
					'conditions' => array(
						'Newsletter.active' => 1,
						'Newsletter.sent' => 0
					),
					'contain' => false
				)
			);

			foreach($runningNewsletters as $id) {
				$this->Newsletter->id = $id;
				$this->Newsletter->saveField('active', 0);
			}

			$this->notice(
				__('All newsletters have been stopped.'),
				array(
					'redirect' => true
				)
			);
		}

		public function admin_mass() {
   			if ($this->MassAction->getAction() == 'send') {
	        	$ids = $this->{$this->modelClass}->find(
	            	'list',
	           			 array(
	               		 'conditions' => array(
	                    $this->{$this->modelClass}->alias . '.active' => 0,
	                    $this->{$this->modelClass}->alias . '.' . $this->{$this->modelClass}->primaryKey => $this->MassAction->getIds($this->MassAction->getAction(), $this->request->data[$this->modelClass])
	                	)
	            	)
	        	);

		        if(empty($ids)) {
		            $this->notice(
		                __d('newsletter', 'Nothing to send'),
		                array(
		                    'level' => 'warning',
		                    'redirect' => ''
		                )
		            );
		        }
 				
 				if (count($ids) == 1) {
 					$message = 'Newsletter is now sending';	
 				} else {
 					$message = 'The Newsletters are now sending';
 				}
 				
        		$this->MassAction->toggle(array_keys($ids), $message);
    		}
 
    		parent::admin_mass();
		}
	}