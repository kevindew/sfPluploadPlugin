<?php
/**
 * BasesfPluploadActions
 *
 * Action for receiving a plupload file transfer
 *
 * @package     sfPluploadPlugin
 * @subpackage  Controller
 * @author      Kevin Dew  <kev@dewsolutions.co.uk>
 */
class BasesfPluploadActions extends sfActions
{ 
  /**
   * Takes the web request and tries to save the uploaded file
   *
   * The web request is expected to have a form and a validator field name
   * passed as arguments. Once an upload is complete this action will initiate
   * the form class and get the validator with the name and pass an array of
   * data through the clean method.
   *
   * data for validator
   * array(
   *   name => $filename
   *   file => $pathToFile
   *   type => $mimeType
   * )
   *
   * Expected to return JSON. The 3 successful states it'll return is
   *
   * incomplete file:
   *    status: incomplate
   *
   * validation error:
   *    status: error
   *    message: $errorMessage
   *
   * comlete file:
   *    status: complete
   *    filename: $fileName (note not path)
   *
   * @see sfActions::execute
   */
  public function executeIndex(sfWebRequest $request)
  {
    $plupload = new sfPluploadUploadedFile(
      $request->getParameter('chunk', 0),
      $request->getParameter('chunks', 0),
      $request->getParameter('name', 'file.tmp')
    );
    
    $plupload->processUpload(
      $request->getFiles($request->getParameter('file-data-name', 'file')),
      $plupload->getContentType($request)
    );
    
    $this->returnData = array();

    if (!$plupload->isComplete())
    {
      $this->returnData = array(
        'status' => 'incomplete'
      );

      return;
    }

    $formClass = $request->getParameter('form');
    $validatorName = $request->getParameter('validator');

    if (!class_exists($formClass))
    {
      throw new Exception('Form class doesn\'t exist');
    }

    $form = new $formClass();

    $validator = $form->getValidator($validatorName);

    try
    {
      $filename = $validator->clean(array(
        'name' => $plupload->getOriginalFilename(),
        'file' => $plupload->getFilePath(),
        'type' => $plupload->getMimeType()
      ));
    }
    catch (sfValidatorError $e)
    {
      $this->returnData = array(
        'status' => 'error',
        'message' => $e->getMessage()
      );
      return;
    }

    $this->returnData = array(
      'status' => 'complete',
      'filename' => $filename
    );
  }
}