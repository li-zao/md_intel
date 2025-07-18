<?php
namespace app\model;

use Exception;

class MimeMailParser
{
    
    /**
     * PHP MimeParser Resource ID
     */
    public $resource;
    
    /**
     * A file pointer to email
     */
    public $stream;
    
    /**
     * A text of an email
     */
    public $data;
    public $parts;
    
    public $relative_start;
    
    /**
     * Stream Resources for Attachments
     */
    public $attachment_streams;
    
    /**
     * Inialize some stuff
     * @return
     */
    public function __construct()
    {
        $this->attachment_streams = [];
    }
    
    /**
     * Free the held resouces
     * @return void
     */
    public function __destruct()
    {
        // clear the email file resource
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        // clear the MailParse resource
        if (is_resource($this->resource)) {
            mailparse_msg_free($this->resource);
        }
        // remove attachment resources
        foreach ($this->attachment_streams as $stream) {
            fclose($stream);
        }
        if ($this->parts) {
            foreach ($this->parts as $part) {
                unset($part);
                $part = null;
            }
            unset($this->parts);
            $this->parts = null;
        }
    }
    
    /**
     * Set the file path we use to get the email text
     * @param $mail_path Object
     * @return Object MimeMailParser Instance
     */
    public function setPath($path)
    {
        // should parse message incrementally from file
        $this->resource = @mailparse_msg_parse_file($path);
        $this->stream = fopen($path, 'rb');
        $this->parse();
        return $this;
    }
    
    /**
     * Set the Stream resource we use to get the email text
     * @param $stream Resource
     * @return Object MimeMailParser Instance
     */
    public function setStream($stream)
    {
        
        // streams have to be cached to file first
        if (get_resource_type($stream) == 'stream') {
            $tmp_fp = tmpfile();
            if ($tmp_fp) {
                while (!feof($stream)) {
                    fwrite($tmp_fp, fread($stream, 2028));
                }
                fseek($tmp_fp, 0);
                $this->stream = &$tmp_fp;
            } else {
                throw new Exception ('Could not create temporary files for attachments. Your tmp directory may be unwritable by PHP.');
                return false;
            }
            fclose($stream);
        } else {
            $this->stream = $stream;
        }
        
        $this->resource = mailparse_msg_create();
        // parses the message incrementally low memory usage but slower
        while (!feof($this->stream)) {
            mailparse_msg_parse($this->resource, fread($this->stream, 2082));
        }
        $this->parse();
        return $this;
    }
    
    /**
     * Set the email text
     * @param $data String
     * @return Object MimeMailParser Instance
     */
    public function setText($data)
    {
        try {
            $this->resource = mailparse_msg_create();
            // does not parse incrementally, fast memory hog might explode
            mailparse_msg_parse($this->resource, $data);
            $this->data = $data;
            $this->parse();
        } catch (Exception $e) {
        }
        return $this;
    }
    
    /**
     */
    public function setInlineSection($path, $start, $end)
    {
        $this->resource = mailparse_msg_create();
        $this->stream = fopen($path, 'r');
        $current = $start;
        $this->relative_start = $start;
        while ($end > $current) {
            fseek($this->stream, $current, SEEK_SET);
            $length = 9600;
            if ($end - $current < 9600)
                $length = $end - $current;
            $filedata = fread($this->stream, $length);
            mailparse_msg_parse($this->resource, $filedata);
            $current = $current + $length;
        }
        $this->parse();
        return $this;
    }
    
    /**
     * Parse the Message into parts
     * @return void
     * @private
     */
    private function parse()
    {
        $structure = mailparse_msg_get_structure($this->resource);
        $this->parts = [];
        foreach ($structure as $part_id) {
            $part = mailparse_msg_get_part($this->resource, $part_id);
            $this->parts [$part_id] = mailparse_msg_get_part_data($part);
        }
    }
    
    /**
     * Retrieve the Email Headers
     * @return Array
     */
    public function getHeaders()
    {
        if (isset ($this->parts [1])) {
            return $this->getPartHeaders($this->parts [1]);
        } else {
            throw new Exception ('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email headers.');
        }
        return false;
    }
    
    public function getHeaderText()
    {
        if (!isset ($this->parts [1])) {
            return false;
        }
        $start = 0;
        $end = $this->parts[1]['starting-pos-body'];
        if ($end == 0) {
            $end = $this->parts[1]['ending-pos'];
        }
        $current = $start;
        fseek($this->stream, $current, SEEK_SET);
        $filedata = fread($this->stream, $end - $current);
        return $filedata;
    }
    
    /**
     * Retrieve a specific Email Header
     * @param $name String Header name
     * @return String
     */
    public function getHeader($name)
    {
        if (isset ($this->parts [1])) {
            $headers = $this->getPartHeaders($this->parts [1]);
            if (isset ($headers [$name])) {
                return $headers [$name];
            }
        } else {
            throw new Exception ('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email headers.');
        }
        return false;
    }
    
    /**
     * Returns the email message body in the specified format
     * @param $type Object[optional]
     * @return Mixed String Body or False if not found
     */
    public function getMessageBodyByType($type = 'text')
    {
        $body = [];
        $mime_types = ['text' => 'text/plain', 'html' => 'text/html'];
        if (in_array($type, array_keys($mime_types))) {
            foreach ($this->parts as $part) {
                if ($this->getPartContentDisposition($part) == 'inline') {
                    continue;
                }
                if (($this->getPartContentType($part) == $mime_types [$type]) && ($this->getPartContentDisposition($part) != 'attachment')) {
                    $charset = $this->getPartCharset($part);
                    $body ['charset'] = $charset;
                    $mailbody = false;
                    if ($this->getPartContentTransferEncoding($part) == 'base64') {
                        $mailbody = base64_decode($this->getPartBody($part));
                    } else if ($this->getPartContentTransferEncoding($part) == "quoted-printable") {
                        $mailbody = $this->getPartBody($part);
                        $mailbody = quoted_printable_decode($mailbody);
                    } else {
                        $mailbody = $this->getPartBody($part);
                    }
                    if ($mailbody != "" && $mailbody != null) {
                        $body ['body'] = $mailbody;
                        break;
                    }
                }
            }
        }
        return $body;
    }
    
    public function getMessageBody()
    {
        $body = $this->getMessageBodyByType('html');
        $body['type'] = 'html';
        if (count($body) != 3) {
            $body = $this->getMessageBodyByType('text');
            $body['type'] = 'text';
        }
        return $body;
    }
    
    public function getMessageBodyCharset()
    {
        foreach ($this->parts as $part) {
            if ((($this->getPartContentType($part) == 'text/html') || ($this->getPartContentType($part) == 'text/plain')) && ($this->getPartContentDisposition($part) != 'attachment')) {
                $charset = $this->getPartCharset($part);
                return $charset;
            }
        }
        return null;
    }
    
    public function getCidByName($cid_name)
    {
        foreach ($this->parts as $part) {
            $cid = $this->getPartContentID($part);
            if ($cid != null) {
                if (strpos(" " . $cid, $cid_name) > 0) {
                    $data = "";
                    if ($this->getPartContentTransferEncoding($part) == 'base64') {
                        $data = base64_decode($this->getPartBody($part));
                    } else {
                        $data = $this->getPartBody($part);
                    }
                    return $data;
                }
            }
        }
        return false;
    }
    
    /**
     * Returns the attachments
     * @param $type Object[optional]
     * @return Array
     */
    public function getAttachments()
    {
        $attachments = [];
        $disposition = 'attachment';
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == $disposition) {
                $attachments [] = base64_decode($this->getPartBody($part));
            }
        }
        return $attachments;
    }
    
    /**
     * Returns the attachments
     * @param $type Object[optional]
     * @return Array
     */
    public function getAttachmentNames()
    {
        $attachmentNames = [];
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == 'attachment') {
                $status = $this->decode_mime($this->getPartDispositionFilename($part));
                if (empty($status)) {
                    $content_type = $this->getPartContentType($part);
                    if ($content_type == 'message/rfc822') {
                        $attachmentNames [] = 'inner.eml';
                    } else {
                        $attachmentNames [] = 'unknown';
                    }
                } else {
                    $attachmentNames [] = $status;
                }
            } else if ($this->getPartContentType($part) == 'application/octet-stream') {
                if (!empty($part['content-name'])) {
                    $attachmentNames [] = $this->decode_mime($part['content-name']);
                }
            }
        }
        return $attachmentNames;
    }
    
    public function getAttachmentsAndNames()
    {
        $attachments = [];
        $disposition = 'attachment';
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == $disposition) {
                $att = [];
                $att['name'] = $this->getPartDispositionFilename($part);
                $att['content'] = base64_decode($this->getPartBody($part));
                $attachments[] = $att;
            } else if ($this->getPartContentType($part) == 'application/octet-stream') {
                $att = [];
                $att['name'] = $this->decode_mime($part['content-name']);
                $att['content'] = base64_decode($this->getPartBody($part));
                $attachments[] = $att;
            }
        }
        return $attachments;
    }
    
    public function getInlineImages()
    {
        $attachments = [];
        $disposition = 'inline';
        foreach ($this->parts as $part) {
            if ($part ['content-type']) {
                $ctype = $part ['content-type'];
                if (substr($ctype, 0, 5) == "image") {
                    $att = [];
                    $att['name'] = $this->getPartDispositionFilename($part);
                    $att['content'] = base64_decode($this->getPartBody($part));
                    $att['Content-ID'] = $this->getPartContentID($part);
                    $attachments[] = $att;
                }
            }
        }
        return $attachments;
    }
    
    /**
     * Returns the inline messages
     * @param $type Object[optional]
     * @return part
     */
    public function getInlineMessage()
    {
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == 'inline') {
                if ($part ['content-type']) {
                    $ctype = $part ['content-type'];
                    if (substr($ctype, 0, 5) == "image") {
                        continue;
                    }
                }
                $inline = [];
                $start = $part ['starting-pos-body'];
                $end = $part ['ending-pos-body'];
                if ($this->relative_start != null) {
                    $start = $start + $this->relative_start;
                    $end = $end + $this->relative_start;
                }
                $inline ['start'] = $start;
                $inline ['end'] = $end;
                
                return $inline;
            }
        }
        return false;
    }
    
    /**
     * Returns the attachment by Name
     * @param $type Object[optional]
     * @return Array
     */
    public function printAttachmentByName($attachName)
    {
        $findAttach = false;
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == 'attachment') {
                if ($attachName == 'inner.eml' || $attachName == 'unknown' || strtolower($this->decode_mime($this->getPartDispositionFilename($part))) == $attachName) {
                    $findAttach = true;
                }
            } else if ($this->getPartContentType($part) == 'application/octet-stream') {
                if (strtolower($this->decode_mime($part['content-name'])) == $attachName) {
                    $findAttach = true;
                }
            }
            if (!$findAttach) {
                continue;
            }
            $start = $part ['starting-pos-body'];
            $end = $part ['ending-pos-body'];
            $current = $start;
            fseek($this->stream, $current, SEEK_SET);
            $filedata = fread($this->stream, $end - $current);
            if ($this->getPartContentTransferEncoding($part) == "base64") {
                $data = base64_decode($filedata);
            } else if ($this->getPartContentTransferEncoding($part) == "quoted-printable") {
                $data = quoted_printable_decode($filedata);
            } else {
                $data = $filedata;
            }
            print ($data);
            flush();
            break;
        }
    }
    
    /**
     * Returns the attachment by Name
     * @param $type Object[optional]
     * @return Array
     */
    public function dumpAttachmentToFile($attachName, $attchfilename)
    {
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == 'attachment') {
                if ($this->decode_mime($this->getPartDispositionFilename($part)) == $attachName) {
                    $start = $part ['starting-pos-body'];
                    $end = $part ['ending-pos-body'];
                    $current = $start;
                    if ($end - $current > 134217728) {
                        $end = $current + 134217728;
                    }
                    fseek($this->stream, $current, SEEK_SET);
                    $filedata = fread($this->stream, $end - $current);
                    if ($this->getPartContentTransferEncoding($part) == "base64") {
                        $data = base64_decode($filedata);
                    } else if ($this->getPartContentTransferEncoding($part) == "quoted-printable") {
                        $data = quoted_printable_decode($filedata);
                    } else {
                        $data = $filedata;
                    }
                    $attchfile = fopen($attchfilename, 'w+');
                    fwrite($attchfile, $data);
                    fclose($attchfile);
                    unset($data);
                    unset($filedata);
                }
            }
        }
    }
    
    /**
     * Returns the attachment by Name
     * @param $type Object[optional]
     * @return Array
     */
    public function getAttachmentPart($attachName)
    {
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == 'attachment' || $this->getPartContentDisposition($part) == 'inline') {
                if ($this->decode_mime($this->getPartDispositionFilename($part)) == $attachName) {
                    return $part;
                }
            }
        }
        return false;
    }
    
    /**
     * Returns the attachments as stream resources (file pointers)
     * @param $type Object[optional]
     * @return Array
     */
    public function getAttachmentsAsStreams()
    {
        $attachments = [];
        foreach ($this->parts as $part) {
            if ($this->getPartContentDisposition($part) == 'attachment' || $this->getPartContentDisposition($part) == 'inline') {
                $attachments [] = $this->decode_mime($this->getAttachmentStream($part));
            }
        }
        array_merge($this->attachment_streams, $attachments);
        return $attachments;
    }
    
    /**
     * Return the Headers for a MIME part
     * @param $part Array
     * @return Array
     */
    public function getPartHeaders($part)
    {
        if (isset ($part ['headers'])) {
            return $part ['headers'];
        }
        return false;
    }
    
    /**
     * Return a Specific Header for a MIME part
     * @param $part   Array
     * @param $header String Header Name
     * @return Array
     */
    public function getPartHeader($part, $header)
    {
        if (isset ($part ['headers'] [$header])) {
            return $part ['headers'] [$header];
        }
        return false;
    }
    
    /**
     * Return the ContentType of the MIME part
     * @param $part Array
     * @return String
     */
    private function getPartContentType($part)
    {
        if (isset ($part ['content-type'])) {
            return $part ['content-type'];
        }
        return false;
    }
    
    private function getPartContentID($part)
    {
        if (isset ($part ['content-id'])) {
            return $part ['content-id'];
        }
        return false;
    }
    
    /**
     * Return the Content Disposition
     * @param $part Array
     * @return String
     */
    private function getPartContentDisposition($part)
    {
        if (isset ($part ['content-disposition'])) {
            return $part ['content-disposition'];
        }
        return false;
    }
    
    /**
     * Return the Content transfer encoding
     * @param $part Array
     * @return String
     */
    private function getPartContentTransferEncoding($part)
    {
        if (isset ($part ['transfer-encoding'])) {
            return $part ['transfer-encoding'];
        }
        return false;
    }
    
    /**
     * Return the Charset
     * @param $part Array
     * @return String
     */
    private function getPartCharset($part)
    {
        if (isset ($part ['charset'])) {
            return $part ['charset'];
        }
        return false;
    }
    
    /**
     * Return the Content disposition filename
     * @param $part Array
     * @return String
     */
    private function getPartDispositionFilename($part)
    {
        if (isset ($part ['disposition-filename'])) {
            return $part ['disposition-filename'];
        }
        if (isset ($part ['content-name'])) {
            return $part ['content-name'];
        }
        return false;
    }
    
    /**
     * Retrieve the Body of a MIME part
     * @param $part Object
     * @return String
     */
    public function getPartBody(&$part)
    {
        $body = '';
        if ($this->stream) {
            $body = $this->getPartBodyFromFile($part);
        } else if ($this->data) {
            $body = $this->getPartBodyFromText($part);
        } else {
            throw new Exception ('MimeMailParser::setPath() or MimeMailParser::setText() must be called before retrieving email parts.');
        }
        return $body;
    }
    
    public function decode_mime_subject($temp)
    {
        return $this->decode_mime($temp);
    }
    
    public function decode_mime($temp)
    {
        if (empty($temp)) {
            return "";
        }
        $mail_string = "";
        if (is_array($temp)) {
            foreach ($temp as $piece) {
                $return_value = $this->decode_mime($piece);
                if (empty($return_value)) {
                    continue;
                }
                if ($mail_string == "") {
                    $mail_string = $return_value;
                } else {
                    $mail_string .= ";" . $return_value;
                }
            }
            return $mail_string;
        }
        $temp = str_replace('"=?', '=?', $temp);
        $temp = str_replace('?="', '?=', $temp);
        $result = @iconv_mime_decode($temp, ICONV_MIME_DECODE_STRICT, 'UTF-8');
        if ($result === false && stripos($temp, 'gb2312') !== false) {
            $temp = str_ireplace('gb2312', 'GB18030', $temp);
            $result = @iconv_mime_decode($temp, ICONV_MIME_DECODE_STRICT, 'UTF-8');
        }
        if ($result === false) {
            return $temp;
        } else {
            return $result;
        }
    }
    
    /**
     * Retrieve the Body from a MIME part from file
     * @param $part Array
     * @return String Mime Body Part
     */
    public function getPartBodyFromFile(&$part)
    {
        $start = $part ['starting-pos-body'];
        $end = $part ['ending-pos-body'];
        if ($this->relative_start != null) {
            $start = $start + $this->relative_start;
            $end = $end + $this->relative_start;
        }
        fseek($this->stream, $start, SEEK_SET);
        $body = "";
        if ($end - $start > 0) {
            $body = fread($this->stream, $end - $start);
        }
        return $body;
    }
    
    /**
     * Retrieve the Body from a MIME part from text
     * @param $part Array
     * @return String Mime Body Part
     */
    private function getPartBodyFromText(&$part)
    {
        $start = $part ['starting-pos-body'];
        $end = $part ['ending-pos-body'];
        if ($this->relative_start != null) {
            $start = $start + $this->relative_start;
            $end = $end + $this->relative_start;
        }
        $body = substr($this->data, $start, $end - $start);
        return $body;
    }
    
    /**
     * Read the attachment Body and save temporary file resource
     * @param $part Array
     * @return String Mime Body Part
     */
    private function getAttachmentStream(&$part)
    {
        $temp_fp = tmpfile();
        if ($temp_fp) {
            if ($this->stream) {
                $start = $part ['starting-pos-body'];
                $end = $part ['ending-pos-body'];
                fseek($this->stream, $start, SEEK_SET);
                $len = $end - $start;
                $written = 0;
                $write = 2028;
                $body = '';
                while ($written < $len) {
                    if (($written + $write < $len)) {
                        $write = $len - $written;
                    }
                    $part = fread($this->stream, $write);
                    fwrite($temp_fp, base64_decode($part));
                    $written += $write;
                }
                fseek($temp_fp, 0, SEEK_SET);
            } else if ($this->text) {
                $attachment = base64_decode($this->getPartBodyFromText($part));
                fwrite($temp_fp, $attachment, strlen($attachment));
            }
        } else {
            throw new Exception ('Could not create temporary files for attachments. Your tmp directory may be unwritable by PHP.');
            return false;
        }
        return $temp_fp;
    }
}
