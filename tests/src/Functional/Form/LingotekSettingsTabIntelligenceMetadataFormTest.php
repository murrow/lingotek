<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek intelligence metadata settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabIntelligenceMetadataFormTest extends LingotekTestBase {

  use IntelligenceMetadataFormTestTrait;

  /**
   * Test intelligence metadata is saved.
   */
  public function testIntelligenceMetadataIsSaved() {
    $this->drupalGet('admin/lingotek/settings');

    $this->assertSession()->responseContains('<summary role="button" aria-controls="edit-intelligence-metadata" aria-expanded="false" aria-pressed="false">Lingotek Intelligence Metadata</summary>');

    // Assert defaults are correct.
    $this->assertIntelligenceFieldDefaults();

    // Check we can store the values.
    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'intelligence_metadata[use_author]' => TRUE,
      'intelligence_metadata[use_author_email]' => TRUE,
      'intelligence_metadata[use_contact_email_for_author]' => FALSE,
      'intelligence_metadata[use_business_unit]' => 1,
      'intelligence_metadata[use_business_division]' => 1,
      'intelligence_metadata[use_campaign_id]' => 1,
      'intelligence_metadata[use_campaign_rating]' => 1,
      'intelligence_metadata[use_channel]' => 1,
      'intelligence_metadata[use_contact_name]' => 1,
      'intelligence_metadata[use_contact_email]' => 1,
      'intelligence_metadata[use_content_description]' => 1,
      'intelligence_metadata[use_external_style_id]' => 1,
      'intelligence_metadata[use_purchase_order]' => 1,
      'intelligence_metadata[use_region]' => 1,
      'intelligence_metadata[use_base_domain]' => 1,
      'intelligence_metadata[use_reference_url]' => 1,
      'intelligence_metadata[default_author_email]' => 'test@example.com',
      'intelligence_metadata[business_unit]' => 'Test Business Unit',
      'intelligence_metadata[business_division]' => 'Test Business Division',
      'intelligence_metadata[campaign_id]' => 'Campaign ID',
      'intelligence_metadata[campaign_rating]' => 5,
      'intelligence_metadata[channel]' => 'Channel Test',
      'intelligence_metadata[contact_name]' => 'Test Contact Name',
      'intelligence_metadata[contact_email]' => 'contact@example.com',
      'intelligence_metadata[content_description]' => 'Content description',
      'intelligence_metadata[external_style_id]' => 'my-style-id',
      'intelligence_metadata[purchase_order]' => 'PO32',
      'intelligence_metadata[region]' => 'region2',
    ];
    $this->submitForm($edit, 'Save Lingotek Intelligence Metadata', 'lingotekintelligence-metadata-form');

    $this->assertSession()->pageTextContains('Lingotek Intelligence Metadata saved correctly.');

    // The values shown are correct.
    $this->assertSession()->checkboxNotChecked('edit-intelligence-metadata-use-contact-email-for-author');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[default_author_email]', 'test@example.com');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[business_unit]', 'Test Business Unit');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[business_division]', 'Test Business Division');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[campaign_id]', 'Campaign ID');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[campaign_rating]', 5);
    $this->assertSession()->fieldValueEquals('intelligence_metadata[channel]', 'Channel Test');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[contact_name]', 'Test Contact Name');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[contact_email]', 'contact@example.com');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[content_description]', 'Content description');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[external_style_id]', 'my-style-id');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[purchase_order]', 'PO32');
    $this->assertSession()->fieldValueEquals('intelligence_metadata[region]', 'region2');

    /** @var \Drupal\lingotek\LingotekIntelligenceMetadataInterface $intelligence */
    $intelligence = \Drupal::service('lingotek.intelligence');
    $this->assertTrue($intelligence->getAuthorPermission());
    $this->assertTrue($intelligence->getAuthorEmailPermission());
    $this->assertFalse($intelligence->getContactEmailForAuthorPermission());
    $this->assertTrue($intelligence->getBusinessUnitPermission());
    $this->assertTrue($intelligence->getBusinessDivisionPermission());
    $this->assertTrue($intelligence->getCampaignIdPermission());
    $this->assertTrue($intelligence->getCampaignRatingPermission());
    $this->assertTrue($intelligence->getChannelPermission());
    $this->assertTrue($intelligence->getContactNamePermission());
    $this->assertTrue($intelligence->getContactEmailPermission());
    $this->assertTrue($intelligence->getContentDescriptionPermission());
    $this->assertTrue($intelligence->getExternalStyleIdPermission());
    $this->assertTrue($intelligence->getPurchaseOrderPermission());
    $this->assertTrue($intelligence->getRegionPermission());
    $this->assertTrue($intelligence->getBaseDomainPermission());
    $this->assertTrue($intelligence->getReferenceUrlPermission());

    $this->assertSame($intelligence->getDefaultAuthorEmail(), 'test@example.com');
    $this->assertSame($intelligence->getBusinessUnit(), 'Test Business Unit');
    $this->assertSame($intelligence->getBusinessDivision(), 'Test Business Division');
    $this->assertSame($intelligence->getCampaignId(), 'Campaign ID');
    $this->assertSame($intelligence->getCampaignRating(), 5);
    $this->assertSame($intelligence->getChannel(), 'Channel Test');
    $this->assertSame($intelligence->getContactName(), 'Test Contact Name');
    $this->assertSame($intelligence->getContactEmail(), 'contact@example.com');
    $this->assertSame($intelligence->getContentDescription(), 'Content description');
    $this->assertSame($intelligence->getExternalStyleId(), 'my-style-id');
    $this->assertSame($intelligence->getPurchaseOrder(), 'PO32');
    $this->assertSame($intelligence->getRegion(), 'region2');
  }

}
