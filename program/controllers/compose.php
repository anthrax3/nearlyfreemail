<?php

namespace Controllers;

class Compose extends Base
{
    // All calls to this controller require login.
    
    protected $require_login = true;
    
    // Create a new message.
    
    public function create()
    {
        // Check additional parameters.
        
        $alias = $this->user->get_default_alias();
        $references = '';
        $recipient = '';
        $cc = '';
        $subject = '';
        $content = '';
        $notes = '';
        
        if ($to = \Common\Request::get('to'))
        {
            if ($to === 'selected')
            {
                $recipient = isset($_SESSION['selected_contacts']) ? $_SESSION['selected_contacts'] : '';
            }
            elseif ($valid_recipient = \Models\Contact::extract($to))
            {
                $recipient = $valid_recipient[0]->get_profile();
            }
        }
        elseif ($id = \Common\Request::get('reply', 'int'))
        {
            if ($message = \Models\Message::get($id)) { if ($message->account_id === $this->user->id)
            {
                $alias_candidate = \Models\Alias::get($message->alias_id);
                if ($alias_candidate && $alias_candidate->account_id == $this->user->id) $alias = $alias_candidate;
                
                $references = trim($message->refs . ' ' . $message->msgid);
                $recipient = $message->reply_to ?: $message->sender;
                $subject = strncasecmp('Re: ', $message->subject, 4) ? ('Re: ' . $message->subject) : $message->subject;
                $content = $this->produce_reply_text($message);
                $notes = 'Reply To: ' . $message->id;
            }}
        }
        elseif ($id = \Common\Request::get('reply_all', 'int'))
        {
            if ($message = \Models\Message::get($id)) { if ($message->account_id === $this->user->id)
            {
                $alias_candidate = \Models\Alias::get($message->alias_id);
                if ($alias_candidate && $alias_candidate->account_id == $this->user->id) $alias = $alias_candidate;
                
                $references = trim($message->refs . ' ' . $message->msgid);
                $recipient = $message->reply_to ?: $message->sender;
                $cc_objects1 = \Models\Contact::extract($message->recipient . ', ' . $message->cc);
                $cc_objects2 = array();
                foreach ($cc_objects1 as $cc_object)
                {
                    if ($cc_object->email !== $alias->email) $cc_objects2[] = $cc_object->get_profile();
                }
                $cc = implode(', ', $cc_objects2);
                $subject = strncasecmp('Re: ', $message->subject, 4) ? ('Re: ' . $message->subject) : $message->subject;
                $content = $this->produce_reply_text($message);
                $notes = 'Reply To: ' . $message->id;
            }}
        }
        elseif ($id = \Common\Request::get('forward', 'int'))
        {
            if ($message = \Models\Message::get($id)) { if ($message->account_id === $this->user->id)
            {
                $alias_candidate = \Models\Alias::get($message->alias_id);
                if ($alias_candidate && $alias_candidate->account_id == $this->user->id) $alias = $alias_candidate;
                
                $references = trim($message->refs . ' ' . $message->msgid);
                $subject = strncasecmp('Fwd: ', $message->subject, 5) ? ('Fwd: ' . $message->subject) : $message->subject;
                $content = $this->produce_reply_text($message);
                $notes = 'Forward Of: ' . $message->id;
            }}
        }
        
        // Add the selected alias's signature.
        
        if ($alias->signature !== '') $content .= "\n\n" . $alias->signature;
        
        // Display the composition form.
        
        $view = new \Common\View('compose');
        $view->title = 'New Message';
        $view->menu = 'compose';
        $view->user = $this->user;
        $view->message = null;
        $view->selected_alias = $alias;
        $view->references = $references;
        $view->recipient = $recipient;
        $view->cc = $cc;
        $view->bcc = '';
        $view->subject = $subject;
        $view->content = $content;
        $view->notes = $notes;
        $view->render();
    }
    
    // Edit a draft.
    
    public function edit($message_id)
    {
        // Check the message ID.
        
        $message = \Models\Message::get($message_id);
        if (!$message || $message->account_id !== $this->user->id) \Common\AJAX::error('Message not found, or access denied.');
        if ($message->is_draft != 1) \Common\AJAX::error('Selected message is not a draft.');
        
        // Find the alias from which the message was originally created. If the alias no longer exists, fall back to the default.
        
        $alias_candidate = \Models\Alias::get($message->alias_id);
        if ($alias_candidate && $alias_candidate->account_id == $this->user->id)
        {
            $alias = $alias_candidate;
        }
        else
        {
            $alias = $this->user->get_default_alias();
        }
        
        // Display the composition form.
        
        $view = new \Common\View('compose');
        $view->title = 'New Message';
        $view->menu = 'compose';
        $view->user = $this->user;
        $view->message = $message;
        $view->selected_alias = $alias;
        $view->render();
    }
    
    // Save a draft.
    
    public function save()
    {
        // Get user input.
        
        $message_id = \Common\Request::post('message_id', 'int');
        $alias_id = \Common\Request::post('alias_id', 'int');
        $recipient = \Common\Request::post('recipient');
        $cc = \Common\Request::post('cc');
        $bcc = \Common\Request::post('bcc');
        $subject = \Common\Request::post('subject');
        $content = \Common\Request::post('message_content');
        $csrf_token = \Common\Request::post('csrf_token');
        
        // Check the CSRF token.
        
        if (!\Common\Session::check_token($csrf_token)) \Common\AJAX::error('CSRF');
        
        // Check the alias ID.
        
        $alias_candidate = \Models\Alias::get($alias_id);
        if ($alias_candidate && $alias_candidate->account_id == $this->user->id)
        {
            $alias = $alias_candidate;
        }
        else
        {
            $alias = $this->user->get_default_alias();
        }
        
        // Everything below should happen in a transaction.
        
        \Common\DB::begin_transaction();
        
        // If creating a new draft.
        
        if (!$message_id)
        {
            // Get back the list of references.
            
            $references = \Common\Request::post('references');
            $notes = \Common\Request::post('notes');
            
            // Create a new message object.
            
            $message = new \Models\Message();
            $message->account_id = $this->user->id;
            $message->alias_id = $alias->id;
            $message->folder_id = \Models\Folder::get_folder($this->user->id, 'Drafts')->id;
            $message->msgid = '';
            $message->sender = $alias->get_profile();
            $message->recipient = $recipient;
            $message->refs = $references;
            $message->cc = $cc;
            $message->bcc = $bcc;
            $message->reply_to = '';
            $message->subject = $subject;
            $message->content = $content;
            $message->charset = 'UTF-8';
            $message->sent_time = 0;
            $message->received_time = time();
            $message->attachments = 0;
            $message->spam_score = 0;
            $message->is_draft = 1;
            $message->is_read = 0;
            $message->is_replied = 0;
            $message->is_starred = 0;
            $message->notes = $notes;
            $message->save();
        }
        
        // If editing an existing draft.
        
        else
        {
            // Find the requested message, and check if it belongs to this user.
            
            $message = \Models\Message::get($message_id);
            if (!$message || $message->account_id !== $this->user->id) \Common\AJAX::error('Message not found, or access denied.');
            if ($message->is_draft != 1) \Common\AJAX::error('Selected message is not a draft.');
            
            // Update the message.
            
            $message->save(array(
                'alias_id' => $alias->id,
                'sender' => $alias->get_profile(),
                'recipient' => $recipient,
                'cc' => $cc,
                'bcc' => $bcc,
                'subject' => $subject,
                'content' => $content,
            ));
        }
        
        // Add or delete attachments.
        
        $count = $message->attachments;
        
        foreach ($_POST as $key => $value)
        {
            if (preg_match('/^attach_delete_(\\d+)$/', $key) && $value === 'yes')
            {
                $success = $message->delete_attachment(substr($key, 14));
                if ($success) $count--;
            }
        }
        
        foreach ($_FILES as $file)
        {
            $name = \Common\Security::filter($file['name'], 'filename');
            $source = is_uploaded_file($file['tmp_name']) ? $file['tmp_name'] : null;
            if ($source)
            {
                $message->add_attachment($name, $source);
                $count++;
            }
        }
        
        if ($count != $message->attachments)
        {
            $message->save(array('attachments' => $count));
        }
        
        // Is everything OK?
        
        \Common\DB::commit();
        
        // Are we saving or sending?
        
        $button = \Common\Request::post('button');
        switch ($button)
        {
            case 'save': \Common\AJAX::redirect(\Common\Router::get_url('/mail/edit', $message->id));
            case 'autosave': \Common\AJAX::content($message->id);
            case 'send': $this->send($message);
            default: \Common\AJAX::error('Unknown Error');
        }
    }
    
    // Send a message.
    
    public function send($message)
    {
        // Check the character encoding of the message.
        
        if (!\Common\Security::validate($message->subject, 'utf-8'))
        {
            $error = 'Your message could not be sent, because the subject is not valid UTF-8.' . "\n\n";
            $error = 'Please return to your draft, remove the invalid characters, and try again.';
            \Common\AJAX::error($error);
        }
        if (!\Common\Security::validate($message->content, 'utf-8'))
        {
            $error = 'Your message could not be sent, because the content is not valid UTF-8.' . "\n\n";
            $error = 'Please return to your draft, remove the invalid characters, and try again.';
            \Common\AJAX::error($error);
        }
        
        // Load SwiftMailer.
        
        load_third_party('swiftmailer');
        $mail = \Swift_Message::newInstance();
        
        // Set basic details.
        
        $alias_candidate = \Models\Alias::get($message->alias_id);
        if ($alias_candidate && $alias_candidate->account_id == $this->user->id)
        {
            $alias = $alias_candidate;
        }
        else
        {
            $alias = $this->user->get_default_alias();
        }
        $mail->setFrom(array($alias->email => $alias->name));
        $mail->setSubject($message->subject);
        $mail->setBody($message->content, 'text/plain', 'UTF-8');
        
        // Replace SwiftMailer's default message ID with something that actually belongs to the domain.
        
        $random = md5(microtime() . $alias->email . \Common\Security::get_random(32));
        $domain = strtolower(substr($alias->email, strrpos($alias->email, '@') + 1));
        $msgid = '<' . base_convert($random, 16, 36) . '.' . dechex(time()) . '@' . $domain . '>';
        
        $headers = $mail->getHeaders();
        $headers->remove('Message-ID');
        $headers->addTextHeader('Message-ID', $msgid);
        
        // Add the user-agent string.
        
        $headers->addTextHeader('User-Agent', 'NearlyFreeMail/' . \VERSION);
        
        // Add some redundant headers, in case somebody wants to reply to NFSN's unremovable Return-Path.
        
        $headers->addTextHeader('Reply-To', $alias->email);
        $mail->setSender($alias->email);
        
        // Add references.
        
        if (strlen($message->refs))
        {
            $in_reply_to = trim(substr($message->refs, strrpos($message->refs, ' ')));
            $headers->addTextHeader('In-Reply-To', $in_reply_to);
            $headers->addTextHeader('References', $message->refs);
        }
        
        // Add recipients.
        
        $recipient_list = array();
        $to = \Models\Contact::extract($message->recipient);
        foreach ($to as $addr)
        {
            $addr->name ? $mail->addTo($addr->email, $addr->name) : $mail->addTo($addr->email);
            $recipient_list[] = $addr->email;
        }
        $cc = \Models\Contact::extract($message->cc);
        foreach ($cc as $addr)
        {
            $addr->name ? $mail->addCc($addr->email, $addr->name) : $mail->addCc($addr->email);
            $recipient_list[] = $addr->email;
        }
        $bcc = \Models\Contact::extract($message->bcc);
        foreach ($bcc as $addr)
        {
            $addr->name ? $mail->addBcc($addr->email, $addr->name) : $mail->addBcc($addr->email);
            $recipient_list[] = $addr->email;
        }
        
        if (!$recipient_list) \Common\AJAX::error('You cannot send a message with no recipients.');
        
        // Add attachments. (This routine obviously needs to be improved.)
        
        if ($message->attachments)
        {
            $attachments = $message->get_attachments();
            foreach ($attachments as $attachment)
            {
                list($filename, $filesize, $content) = $message->get_attachment($attachment->id);
                if (is_resource($content)) $content = stream_get_contents($content);
                $mail->attach(\Swift_Attachment::newInstance($content, $filename, \Common\MIME::from_filename($filename)));
            }
        }
        
        // Send!
        
        $mailer = \Swift_Mailer::newInstance(\Swift_MailTransport::newInstance());
        $result = $mailer->send($mail, $failures);
        if (!$result) \Common\AJAX::error('Failed to send message.');
        
        // Update the message, and any related messages.
        
        \Common\DB::begin_transaction();
        
        $message->mark_as_read();
        $message->mark_as_sent();
        $message->move_to_folder(\Models\Folder::get_folder($this->user->id, 'Sent')->id);
        $message->save(array('msgid' => $msgid, 'sent_time' => time()));
        $message->set_original_and_source($message->subject, $message->content, $mail->toString());
        
        if ($message->notes !== '')
        {
            if (substr($message->notes, 0, 10) === 'Reply To: ')
            {
                $other_message = \Models\Message::get((int)substr($message->notes, 10));
                if ($other_message && $other_message->account_id == $this->user->id) $other_message->mark_as_replied();
            }
            if (substr($message->notes, 0, 12) === 'Forward Of: ')
            {
                $other_message = \Models\Message::get((int)substr($message->notes, 12));
                if ($other_message && $other_message->account_id == $this->user->id) $other_message->mark_as_forwarded();
            }
        }
        
        // Update the last-used timestamp of any contacts to which this message was sent.
        
        \Models\Contact::update_last_used_timestamp($recipient_list);
        
        // Finish!
        
        \Common\DB::commit();
        \Common\AJAX::redirect(\Common\Router::get_url('/mail'));
    }
    
    // Produce a reply text.
    
    public function produce_reply_text($message)
    {
        $sender = \Models\Contact::extract($message->sender);
        $sender_name = $sender ? $sender[0]->name : '(no name)';
        $header = 'On ' . date('D, d M Y H:i:s T', $message->sent_time) . ', ' . $sender_name . ' wrote:';
        
        $content = explode("\n", trim($message->content));
        $content = array_map(function($str) { return "> $str"; }, $content);
        return "\n\n\n" . $header . "\n" . implode("\n", $content) . "\n";
    }
}
