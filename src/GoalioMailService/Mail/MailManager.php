<?php
namespace GoalioMailService\Mail;

use Zend\Mail\Address\AddressInterface;
use Zend\Mail\AddressList;
use Zend\Mail\Transport\TransportInterface;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\Response;
use Zend\View\Model\ViewModel;
use Zend\Mail\Message as MailMessage;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Mime;
use Zend\Mime\Part as MimePart;
use Zend\View\View;
use GoalioMailService\Mail\Exception;
use Traversable;

class MailManager {

    const ENCODING_UTF_8 = 'utf-8';

    /**
     * @var TransportInterface
     */
    protected $transport;

    /**
     * @var View
     */
    protected $view;

    /**
     * @var string
     */
    protected $encoding = self::ENCODING_UTF_8;

    /**
     * @var string
     */
    protected $textLayout;

    /**
     * @var string
     */
    protected $htmlLayout;

    /**
     * @var array
     */
    protected $options = array();


    /**
     * @return TransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param TransportInterface $transport
     * @return $this
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * @return View
     */
    public function getView()
    {
        return $this->view;
    }

    /**
     * @param View $view
     */
    public function setView($view)
    {
        $this->view = $view;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param string $encoding
     */
    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
    }

    /**
     * @return string
     */
    public function getHtmlLayout()
    {
        return $this->htmlLayout;
    }

    /**
     * @param string $htmlLayout
     */
    public function setHtmlLayout($htmlLayout)
    {
        $this->htmlLayout = $htmlLayout;
    }

    /**
     * @return string
     */
    public function getTextLayout()
    {
        return $this->textLayout;
    }

    /**
     * @param string $textLayout
     */
    public function setTextLayout($textLayout)
    {
        $this->textLayout = $textLayout;
    }


    public function setOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (!is_array($options)) {
            throw new Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['encoding'])) {
            $this->setEncoding($options['encoding']);
        }

        if (isset($options['layout_html'])) {
            $this->setHtmlLayout($options['layout_html']);
        }

        if (isset($options['layout_text'])) {
            $this->setTextLayout($options['layout_text']);
        }

        $this->options = $options;

        return $this;
    }

    /**
     * @param MailMessage $message
     * @return mixed
     */
    public function send(MailMessage $message)
    {
        return $this->getTransport()->send($message);
    }


    /**
     * @param string|AddressInterface|array|AddressList|Traversable $from
     * @param string|null $fromName
     * @param string|AddressInterface|array|AddressList|Traversable $to
     * @param string $subject
     * @param string|ViewModel|null $htmlModel
     * @param string|ViewModel|null $textModel
     * @param array|Traversable $attachements
     * @param array|Traversable $values
     * @return MailMessage
     */
    public function createMessage($from, $fromName = null, $to, $subject, $htmlModel = null, $textModel = null, $attachements = array(), $values = array())
    {
        $mail = new MailMessage();
        $mail->setEncoding($this->getEncoding());
        $mail->setFrom($from, $fromName);
        $mail->setTo($to);
        $mail->setSubject($subject);
        $mail->setBody($this->createBody($htmlModel, $textModel, $attachements, $values));

        return $mail;
    }

    /**
     * @param string|ViewModel $htmlModel
     * @param string|ViewModel $textModel
     * @param array $attachements
     * @param array $values
     * @return MimeMessage
     */
    public function createBody($htmlModel = null, $textModel = null, $attachements = array(), $values = array())
    {
        $body = new MimeMessage();

        // HTML
        if($htmlModel) {
            $content = $this->renderHtml($htmlModel, $values);
            $part = new MimePart($content);
            $part->type = Mime::TYPE_HTML; // TODO Evaluate "text/html; charset=UTF-8";
            $body->addPart($part);
        }

        // Text
        if($textModel) {
            $content = $this->renderHtml($htmlModel, $values);
            $part = new MimePart($content);
            $part->type = Mime::TYPE_TEXT;
            $body->addPart($part);
        }

        // Attachments
        foreach($attachements as $filename => $content) {
            $file = new MimePart($content);
            $file->filename = $filename;
            $body->addPart($file);
        }

        return $body;
    }

    /**
     * @param string|ViewModel $nameOrModel
     * @param array $values
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    public function renderHtml($nameOrModel, $values = array()) {
        if(is_string($nameOrModel)) {
            $name = $nameOrModel;
            $nameOrModel = new ViewModel($values);
            $nameOrModel->setTemplate($name);
        }
        if(!$nameOrModel instanceof ViewModel) {
            throw new Exception\InvalidArgumentException(sprintf(
                'nameOrModel must be a string or an instance of Zend\View\ViewModel. Given %s', get_class($nameOrModel)
            ));
        }

        $layoutViewModel = new ViewModel();
        $layoutViewModel->setTemplate($this->getHtmlLayout());
        $layoutViewModel->addChild($nameOrModel);

        $response = new Response();
        $view = $this->getView();
        $view->setResponse($response);
        $view->render($layoutViewModel);

        return $response->getContent();
    }

    /**
     * @param string|ViewModel $nameOrModel
     * @param array $values
     * @return string
     * @throws Exception\InvalidArgumentException
     */

    public function renderText($nameOrModel, $values = array()) {
        if(is_string($nameOrModel)) {
            $name = $nameOrModel;
            $nameOrModel = new ViewModel($values);
            $nameOrModel->setTemplate($name);
        }
        if(!$nameOrModel instanceof ViewModel) {
            throw new Exception\InvalidArgumentException(sprintf(
                'nameOrModel must be a string or an instance of Zend\View\ViewModel. Given %s', get_class($nameOrModel)
            ));
        }

        $layoutViewModel = new ViewModel();
        $layoutViewModel->setTemplate($this->getTextLayout());
        $layoutViewModel->addChild($nameOrModel);

        $response = new Response();
        $view = $this->getView();
        $view->setResponse($response);
        $view->render($layoutViewModel);

        return $response->getContent();
    }


    /**
     * Return a HTML message ready to be sent
     *
     * @param array|string $from A string containing the sender e-mail address, or if array with keys email and name
     * @param array|string $to An array containing the recipients of the mail
     * @param string $subject Subject of the mail
     * @param string|\Zend\View\Model\ModelInterface $nameOrModel Either the template to use, or a ViewModel
     * @param null|array $values Values to use when the template is rendered
     * @return MailMessage
     */
    public function createHtmlMessage($from, $to, $subject, $nameOrModel, $values = array())
    {
        return $this->createMessage($from['email'], $from['name'], $to, $subject, $nameOrModel, null, array(), $values);
    }

    /**
     * Return a text message ready to be sent
     *
     * @param array|string $from A string containing the sender e-mail address, or if array with keys email and name
     * @param array|string $to An array containing the recipients of the mail
     * @param string $subject Subject of the mail
     * @param string|\Zend\View\Model\ModelInterface $nameOrModel Either the template to use, or a ViewModel
     * @param null|array $values Values to use when the template is rendered
     * @return MailMessage
     * @deprecated
     */
    public function createTextMessage($from, $to, $subject, $nameOrModel, $values = array())
    {
        return $this->createMessage($from['email'], $from['name'], $to, $subject, null, $nameOrModel, array(), $values);
    }





}