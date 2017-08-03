<?php

namespace Courier\Test;

use Courier\SparkPostCourier;
use Courier\Test\Support\TestContent;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Http\Client\Exception\HttpException;
use Mockery;
use PhpEmail\Address;
use PhpEmail\Attachment\FileAttachment;
use PhpEmail\Content\EmptyContent;
use PhpEmail\Content\SimpleContent;
use PhpEmail\Content\TemplatedContent;
use PhpEmail\Email;
use SparkPost\SparkPost;
use SparkPost\SparkPostException;
use SparkPost\SparkPostPromise;
use SparkPost\SparkPostResponse;
use SparkPost\Transmission;

/**
 * @covers \Courier\SparkPostCourier
 * @covers \Courier\Exceptions\TransmissionException
 * @covers \Courier\Exceptions\UnsupportedContentException
 */
class SparkPostCourierTest extends TestCase
{
    /**
     * @var string
     */
    private static $file = '/tmp/sparkpost_attachment_test.txt';

    /**
     * @var Mockery\Mock|SparkPost
     */
    private $sparkPost;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        file_put_contents(self::$file, 'Attachment file');
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        unlink(self::$file);
    }

    public function setUp()
    {
        $this->sparkPost = Mockery::mock(SparkPost::class);
    }

    /**
     * @testdox It should send a simple email
     */
    public function sendsSimpleEmail()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            SimpleContent::text('This is a test email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $expectedArray = [
            'content'    => [
                'from'        => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'     => 'Subject',
                'html'        => null,
                'text'        => 'This is a test email',
                'attachments' => [],
                'reply_to'    => null,
            ],
            'recipients' => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, new SparkPostResponse(new Response(200)));

        $courier->deliver($email);
    }

    /**
     * @testdox It should send an empty email
     */
    public function sendsEmptyEmail()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $expectedArray = [
            'content'    => [
                'from'        => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'     => 'Subject',
                'html'        => '',
                'text'        => '',
                'attachments' => [],
                'reply_to'    => null,
            ],
            'recipients' => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, new SparkPostResponse(new Response(200)));

        $courier->deliver($email);
    }

    /**
     * @testdox It should send a templated email
     */
    public function sendsTemplatedEmail()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addReplyTos(new Address('reply.to@test.com'));

        $expectedArray = [
            'content'           => [
                'template_id' => '1234',
            ],
            'substitution_data' => [
                'test'        => 'value',
                'fromName'    => null,
                'fromEmail'   => 'sender',
                'fromDomain'  => 'test.com',
                'subject'     => 'Subject',
                'replyTo'     => 'reply.to@test.com',
            ],
            'recipients'        => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, new SparkPostResponse(new Response(200)));

        $courier->deliver($email);
    }

    /**
     * @testdox It should support sending a templated email with attachments
     */
    public function sendsTemplatedEmailWithAttachment()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from'     => [
                        'email' => 'template.sender@test.com',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '"Template Replier" <template.replier@test.com>',
                    'html'     => 'This is a template html test',
                    'headers'  => ['X-Header' => 'test'],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        $expectedArray = [
            'content'           => [
                'from'        => [
                    'email' => 'template.sender@test.com',
                    'name'  => 'Template Address',
                ],
                'subject'     => 'Template Subject',
                'html'        => 'This is a template html test',
                'text'        => null,
                'attachments' => [
                    [
                        'name' => 'file name.txt',
                        'type' => mime_content_type(self::$file),
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'    => '"Template Replier" <template.replier@test.com>',
                'headers'     => ['X-Header' => 'test'],
            ],
            'substitution_data' => [
                'test'        => 'value',
                'fromName'    => null,
                'fromEmail'   => 'sender',
                'fromDomain'  => 'test.com',
                'subject'     => 'Subject',
            ],
            'recipients'        => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, new SparkPostResponse(new Response(200)));

        $courier->deliver($email);
    }

    /**
     * @testdox It should support sending a templated email with an attachment and a templated from/replyTo
     */
    public function handlesTemplatedEmailsWithAttachmentAndDynamicSender()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addReplyTos(new Address('dynamic@replyto.com'));

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from'     => [
                        'email' => '{{fromEmail}}@{{fromDomain}}',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '{{replyTo}}',
                    'html'     => 'This is a template html test',
                    'headers'  => ['X-Header' => 'test'],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        $expectedArray = [
            'content'           => [
                'from'        => [
                    'email' => 'sender@test.com',
                    'name'  => null,
                ],
                'subject'     => 'Template Subject',
                'html'        => 'This is a template html test',
                'text'        => null,
                'attachments' => [
                    [
                        'name' => 'file name.txt',
                        'type' => mime_content_type(self::$file),
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'    => 'dynamic@replyto.com',
                'headers'     => ['X-Header' => 'test'],
            ],
            'substitution_data' => [
                'test'        => 'value',
                'fromName'    => null,
                'fromEmail'   => 'sender',
                'fromDomain'  => 'test.com',
                'subject'     => 'Subject',
                'replyTo'     => 'dynamic@replyto.com',
            ],
            'recipients'        => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, new SparkPostResponse(new Response(200)));

        $courier->deliver($email);
    }

    /**
     * @testdox It should throw an error if the reply to is a templated value but not provided on the email
     * @expectedException \Courier\Exceptions\ValidationException
     */
    public function handlesDynamicTemplateMissingSender()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $expectedTemplate = [
            'results' => [
                'content' => [
                    'from'     => [
                        'email' => '{{fromEmail}}@{{fromDomain}}',
                        'name'  => 'Template Address',
                    ],
                    'subject'  => 'Template Subject',
                    'reply_to' => '{{replyTo}}',
                    'html'     => 'This is a template html test',
                    'headers'  => ['X-Header' => 'test'],
                ],
            ],
        ];

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andReturn(new SparkPostResponse(new Response(200, [], json_encode($expectedTemplate))));

        $courier->deliver($email);
    }

    /**
     * @testdox It should handle errors when searching for a template
     * @expectedException \Courier\Exceptions\TransmissionException
     */
    public function handlesTemplateRetrievalErrors()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TemplatedContent('1234', ['test' => 'value']),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->addAttachments(new FileAttachment(self::$file, 'file name.txt'));

        $exception = new HttpException('Message', new Request('GET', 'stuff'), new Response(400, [], ''));

        $this->sparkPost
            ->shouldReceive('syncRequest')
            ->once()
            ->with('GET', 'templates/1234')
            ->andThrow(new SparkPostException($exception));

        $courier->deliver($email);
    }

    /**
     * @testdox It should support all Email values
     */
    public function supportsAllValues()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'This is the Subject',
            new SimpleContent('This is the html email', 'This is the text email'),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $email->setReplyTos(new Address('replyTo@test.com'));
        $email->setCcRecipients(new Address('cc@test.com', 'CC'));
        $email->setBccRecipients(new Address('bcc@test.com', 'BCC'));
        $email->setAttachments(new FileAttachment(self::$file));

        $expectedArray = [
            'content'    => [
                'from'        => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'     => 'This is the Subject',
                'html'        => 'This is the html email',
                'text'        => 'This is the text email',
                'attachments' => [
                    [
                        'name' => basename(self::$file),
                        'type' => mime_content_type(self::$file),
                        'data' => base64_encode(file_get_contents(self::$file)),
                    ],
                ],
                'reply_to'    => 'replyTo@test.com',
            ],
            'recipients' => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
            'cc' => [
                [
                    'address' => [
                        'name'  => 'CC',
                        'email' => 'cc@test.com',
                    ],
                ],
            ],
            'bcc' => [
                [
                    'address' => [
                        'name'  => 'BCC',
                        'email' => 'bcc@test.com',
                    ],
                ],
            ],
        ];

        $this->setExpectedCall($expectedArray, new SparkPostResponse(new Response(200)));

        $courier->deliver($email);
    }

    /**
     * @testdox It should handle an error transmitting the email to SparkPost
     * @expectedException \Courier\Exceptions\TransmissionException
     */
    public function handlesTransmissionErrors()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new EmptyContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $expectedArray = [
            'content'    => [
                'from'        => [
                    'name'  => null,
                    'email' => 'sender@test.com',
                ],
                'subject'     => 'Subject',
                'html'        => '',
                'text'        => '',
                'attachments' => [],
                'reply_to'    => null,
            ],
            'recipients' => [
                [
                    'address' => [
                        'name'  => null,
                        'email' => 'recipient@test.com',
                    ],
                ],
            ],
        ];

        $exception = new HttpException('Message', new Request('GET', 'stuff'), new Response(400, [], ''));
        $this->setExpectedCall($expectedArray, new SparkPostException($exception));

        $courier->deliver($email);
    }

    /**
     * @testdox It should validate the input content is deliverable
     * @expectedException \Courier\Exceptions\UnsupportedContentException
     */
    public function validatesSupportedContent()
    {
        $courier = new SparkPostCourier($this->sparkPost);

        $email = new Email(
            'Subject',
            new TestContent(),
            new Address('sender@test.com'),
            [new Address('recipient@test.com')]
        );

        $courier->deliver($email);
    }

    /**
     * @param $expectedArray
     * @param SparkPostResponse|SparkPostException $response
     */
    private function setExpectedCall($expectedArray, $response)
    {
        /** @var Mockery\Mock|Transmission $transmission */
        $transmission = Mockery::mock(Transmission::class);

        /** @var Mockery\Mock|SparkPostPromise $promise */
        $promise = Mockery::mock(SparkPostPromise::class);

        $this->sparkPost->transmissions = $transmission;

        if ($response instanceof SparkPostException) {
            $promise
                ->shouldReceive('wait')
                ->once()
                ->andThrow($response);
        } else {
            $promise
                ->shouldReceive('wait')
                ->once()
                ->andReturn($response);
        }

        $transmission
            ->shouldReceive('post')
            ->once()
            ->with($expectedArray)
            ->andReturn($promise);
    }
}