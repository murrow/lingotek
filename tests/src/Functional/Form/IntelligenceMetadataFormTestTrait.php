<?php

namespace Drupal\Tests\lingotek\Functional\Form;

/**
 * Utility methods for testing the intelligence metadata forms.
 *
 * @package Drupal\Tests\lingotek\Functional\Form
 */
trait IntelligenceMetadataFormTestTrait {

  /**
   * Assert field defaults are correct.
   */
  protected function assertIntelligenceFieldDefaults() {
    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-author');
    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-author-email');
    $this->assertSession()->checkboxNotChecked('edit-intelligence-metadata-use-contact-email-for-author');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-business-unit');
    $this->assertSession()->fieldExists('intelligence_metadata[business_unit]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-business-division');
    $this->assertSession()->fieldExists('intelligence_metadata[business_division]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-campaign-id');
    $this->assertSession()->fieldExists('intelligence_metadata[campaign_id]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-campaign-rating');
    $this->assertSession()->fieldExists('intelligence_metadata[campaign_rating]', '0');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-channel');
    $this->assertSession()->fieldExists('intelligence_metadata[channel]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-contact-name');
    $this->assertSession()->fieldExists('intelligence_metadata[contact_name]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-contact-email');
    $this->assertSession()->fieldExists('intelligence_metadata[contact_email]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-content-description');
    $this->assertSession()->fieldExists('intelligence_metadata[content_description]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-base-domain');
    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-reference-url');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-external-style-id');
    $this->assertSession()->fieldExists('intelligence_metadata[external_style_id]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-purchase-order');
    $this->assertSession()->fieldExists('intelligence_metadata[purchase_order]', '');

    $this->assertSession()->checkboxChecked('edit-intelligence-metadata-use-region');
    $this->assertSession()->fieldExists('intelligence_metadata[region]', '');
  }

}
