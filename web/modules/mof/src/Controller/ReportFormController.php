<?php declare(strict_types=1);

namespace Drupal\mof\Controller;

use Drupal\webform\Controller\WebformEntityController;
use Drupal\webform\WebformInterface;
use Symfony\Component\HttpFoundation\Request;

final class ReportFormController extends WebformEntityController {

  /**
   * {@inheritdoc}
   */
  public function addForm(Request $request, WebformInterface $webform) {
    $element = $webform->getElementsOriginalDecoded()['model_name'];
    $element['#default_value'] = $request->attributes->get('model')->label();
    $webform->setElementProperties('model_name', $element);
    return parent::addForm($request, $webform);
  } 

}

