# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: mcp-google.spec.mjs >> MCP E2E-08 business consent and reviewed campaigns share the web domain
- Location: tests\e2e\mcp-google.spec.mjs:489:1

# Error details

```
Error: contacts_create

expect(received).toBe(expected) // Object.is equality

Expected: false
Received: true
```

# Test source

```ts
  416 |   const adminPage = await adminContext.newPage();
  417 |   await adminPage.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
  418 |   await localLogin(adminPage, data.emails.other);
  419 |   await switchAccount(adminPage, String(data.account_b));
  420 |   await adminPage.goto('/team');
  421 |   adminPage.once('dialog', dialog => dialog.accept());
  422 |   await adminPage.getByRole('row').filter({ hasText: data.emails.owner }).getByRole('button', { name: 'Retirar acceso', exact: true }).click();
  423 |   await expect(adminPage.getByRole('row').filter({ hasText: data.emails.owner })).toHaveCount(0);
  424 |   expect((await mcp(renewed, 'tools/list')).status()).toBe(401);
  425 |   expect((await mcp(connectionA, 'tools/list')).status()).toBe(200);
  426 |   const removed = await startConsent(page, data, 'contacts.read');
  427 |   await expect(page.getByRole('button', { name: 'Autorizar Browser delegated B', exact: true })).toHaveCount(0);
  428 |   await page.getByRole('button', { name: 'Denegar conexión', exact: true }).click();
  429 |   expect((await removed.completed).denied).toBe(true);
  430 |   await adminContext.close();
  431 |   await Promise.all([connectionA.client.dispose(), connectionB.client.dispose(), expiring.client.dispose(), renewed.client.dispose(), removed.client.dispose()]);
  432 | });
  433 | 
  434 | test('MCP E2E-07 consent authorizes data tools, keyset reads and safe template preview', async ({ page }, testInfo) => {
  435 |   const data = fixture();
  436 |   await page.route('**/*', route => [origin, callbackOrigin].includes(new URL(route.request().url()).origin) ? route.continue() : route.abort());
  437 |   await localLogin(page, data.emails.owner);
  438 |   const consent = await startConsent(page, data, 'contacts.read contacts.write templates.read templates.write');
  439 |   const form = page.getByRole('form', { name: 'Autorizar Browser owner own', exact: true });
  440 |   await expect(form.getByRole('checkbox', { name: 'Consultar plantillas', exact: true })).toBeChecked();
  441 |   await expect(form.getByRole('checkbox', { name: 'Editar plantillas', exact: true })).toBeChecked();
  442 |   await page.screenshot({ path: testInfo.outputPath('mcp-data-consent.png'), fullPage: true });
  443 |   const connection = await approve(page, consent, 'Browser owner own');
  444 |   const contacts = [];
  445 |   let templateId;
  446 |   const call = async (name, args = {}) => {
  447 |     const response = await mcp(connection, 'tools/call', { name, arguments: args });
  448 |     expect(response.status(), name).toBe(200);
  449 |     const result = (await response.json()).result;
  450 |     expect(result.isError, name).toBe(false);
  451 |     return result.structuredContent;
  452 |   };
  453 |   try {
  454 |     const listed = await mcp(connection, 'tools/list');
  455 |     expect(listed.status()).toBe(200);
  456 |     expect((await listed.json()).result.tools).toHaveLength(20);
  457 |     for (const suffix of ['a', 'b']) {
  458 |       const created = await call('contacts_create', { contact: { email: `mcp-data-${data.run}-${suffix}@example.test`, name: 'Browser data fixture', consent_status: 'unknown' } });
  459 |       contacts.push(created.data.contact.id);
  460 |     }
  461 |     const first = await call('contacts_list', { search: `mcp-data-${data.run}`, limit: 1 });
  462 |     expect(first.data.items.map(item => item.id)).toEqual([contacts[1]]);
  463 |     expect(first.page.has_more).toBe(true);
  464 |     const second = await call('contacts_list', { search: `mcp-data-${data.run}`, cursor: first.page.next_cursor, limit: 100 });
  465 |     expect(second.data.items.map(item => item.id)).toEqual([contacts[0]]);
  466 |     expect(second.page.has_more).toBe(false);
  467 |     const created = await call('templates_create', { template: { name: 'Browser MCP data template', subject: 'Hello {{contact.name}}', html: '<p>Hello {{contact.name}}</p><img src="https://example.invalid/pixel"><script>alert(1)</script>' } });
  468 |     templateId = created.data.template.id;
  469 |     const preview = await call('templates_preview', { source: { type: 'stored', template_id: templateId }, sample: { name: '<Browser fixture>', email: 'preview@example.test' } });
  470 |     expect(preview.data.preview.subject).toBe('Hello <Browser fixture>');
  471 |     expect(preview.data.preview.html).toContain('&lt;Browser fixture&gt;');
  472 |     expect(preview.data.preview.document).not.toContain('<script');
  473 |     expect(preview.data.preview.document).not.toContain('example.invalid/pixel');
  474 |     await safeError(await mcp(connection, 'tools/call', { name: 'templates_get', arguments: { template_id: templateId, account_id: data.account_b } }), 'validation_failed');
  475 |     await page.goto('/settings/connections');
  476 |     await expect(page.getByText('templates_preview · succeeded', { exact: false }).first()).toBeVisible();
  477 |     await page.screenshot({ path: testInfo.outputPath('mcp-data-audit.png'), fullPage: true });
  478 |   } finally {
  479 |     // These are created by this test; the SQL owner also cascades only its fixture accounts on failure.
  480 |     try {
  481 |       if (templateId) await call('templates_delete', { template_id: templateId });
  482 |       for (const id of contacts) await call('contacts_delete', { contact_id: id });
  483 |     } finally {
  484 |       await connection.client.dispose();
  485 |     }
  486 |   }
  487 | });
  488 | 
  489 | test('MCP E2E-08 business consent and reviewed campaigns share the web domain', async ({ page }, testInfo) => {
  490 |   const data = fixture();
  491 |   await page.route('**/*', route => [origin, callbackOrigin].includes(new URL(route.request().url()).origin) ? route.continue() : route.abort());
  492 |   await localLogin(page, data.emails.owner);
  493 |   await switchAccount(page, String(data.account_a));
  494 |   await page.goto('/settings/mail');
  495 |   await page.getByRole('button', { name: 'Nuevo remitente', exact: true }).click();
  496 |   const senderForm = page.locator('form').filter({ has: page.getByRole('button', { name: 'Guardar remitente', exact: true }) });
  497 |   const senderEmail = `business-sender-${data.run}@example.test`;
  498 |   await senderForm.getByLabel('Email', { exact: true }).fill(senderEmail);
  499 |   await senderForm.getByLabel('Nombre', { exact: true }).fill('Synthetic business sender');
  500 |   await senderForm.getByRole('checkbox', { name: 'Autorizo este remitente para log y Mailpit', exact: true }).check();
  501 |   await senderForm.getByRole('button', { name: 'Guardar remitente', exact: true }).click();
  502 |   const senderRow = page.locator('li').filter({ hasText: senderEmail });
  503 |   await expect(senderRow).toContainText('Autorizado para laboratorio');
  504 |   const senderKey = await senderRow.getAttribute('wire:key');
  505 |   expect(senderKey).toMatch(/^sender-[1-9][0-9]*$/);
  506 |   const senderId = Number(senderKey.slice('sender-'.length));
  507 |   const consent = await startConsent(page, data, 'contacts.read contacts.write templates.read templates.write campaigns.read campaigns.write mail.send suppressions.read suppressions.write activity.read');
  508 |   await expect(page.getByRole('form', { name: 'Autorizar Browser owner own', exact: true }).getByRole('checkbox', { checked: true })).toHaveCount(10);
  509 |   await page.screenshot({ path: testInfo.outputPath('mcp-business-consent.png'), fullPage: true });
  510 |   const connection = await approve(page, consent, 'Browser owner own');
  511 |   let reader;
  512 |   const call = async (name, args = {}) => {
  513 |     const response = await mcp(connection, 'tools/call', { name, arguments: args });
  514 |     expect(response.status(), name).toBe(200);
  515 |     const result = (await response.json()).result;
> 516 |     expect(result.isError, name).toBe(false);
      |                                  ^ Error: contacts_create
  517 |     expect(JSON.parse(result.content[0].text)).toEqual(result.structuredContent);
  518 |     return result.structuredContent;
  519 |   };
  520 |   try {
  521 |     expect((await (await mcp(connection, 'tools/list', { per_page: 100 })).json()).result.tools).toHaveLength(34);
  522 |     const contact = (await call('contacts_create', { contact: { email: `business-recipient-${data.run}@example.test`, name: '<Browser business fixture>', consent_status: 'granted', consent_source: 'Synthetic browser form', consented_at: new Date(Date.now() - 60000).toISOString().slice(0, 16) } })).data.contact;
  523 |     const template = (await call('templates_create', { template: { name: `Business template ${data.run}`, subject: 'Hello {{ contact.name }}', html: '<p>Hello {{ contact.name }}</p>', text: 'Hello {{ contact.email }}' } })).data.template;
  524 |     const campaignName = `Browser MCP business ${data.run}`;
  525 |     const fields = { name: campaignName, template_id: template.id, sender_id: senderId, list_ids: [], contact_ids: [contact.id] };
  526 |     const created = (await call('campaigns_create', { campaign: fields })).data.campaign;
  527 |     const updated = (await call('campaigns_update', { campaign_id: created.id, expected_version: created.version, campaign: fields })).data.campaign;
  528 |     const review = (await call('campaigns_review', { campaign_id: created.id, expected_version: updated.version })).data;
  529 |     expect(review.campaign.status).toBe('reviewed');
  530 |     expect(review.revision.audience.recipient_count).toBe(1);
  531 |     expect(review.revision.preview.html).toContain('&lt;Browser business fixture&gt;');
  532 |     expect(review.revision).not.toHaveProperty('sender');
  533 |     const detail = (await call('campaigns_get', { campaign_id: created.id, include: { composition: true, content: true, sender: true, preview: true, recipients: {} } })).data;
  534 |     expect(detail.revision.sender.id).toBe(senderId);
  535 |     expect(detail.revision.audience.items.map(item => item.contact_id)).toEqual([contact.id]);
  536 |     const authorized = (await call('campaigns_authorize', { campaign_id: created.id, expected_version: review.campaign.version, revision_id: review.revision.id, confirmed: true })).data.campaign;
  537 |     const paused = (await call('campaigns_pause', { campaign_id: created.id, expected_version: authorized.version })).data.campaign;
  538 |     await page.goto('/campaigns');
  539 |     await expect(page.locator('li').filter({ hasText: campaignName })).toContainText('Pausada');
  540 |     await page.screenshot({ path: testInfo.outputPath('mcp-business-campaigns.png'), fullPage: true });
  541 |     const resumed = (await call('campaigns_resume', { campaign_id: created.id, expected_version: paused.version })).data.campaign;
  542 |     expect(resumed.status).toBe('authorized');
  543 |     expect(resumed.reviewed_revision_id).toBe(review.revision.id);
  544 |     expect((await call('campaigns_list')).data.items.some(item => item.id === created.id)).toBe(true);
  545 |     await safeError(await mcp(connection, 'tools/call', { name: 'campaigns_pause', arguments: { campaign_id: created.id, expected_version: paused.version } }), 'version_conflict');
  546 |     const suppression = (await call('suppressions_add', { email: contact.email })).data.suppression;
  547 |     expect((await call('suppressions_list')).data.items.some(item => item.id === suppression.id)).toBe(true);
  548 |     expect((await call('suppressions_remove', { suppression_id: suppression.id })).data.deleted).toBe(true);
  549 |     expect((await call('activity_metrics')).data.delivery_feedback).toBe('not_available');
  550 |     expect((await call('activity_list')).data.items).toEqual([]);
  551 |     await safeError(await mcp(connection, 'tools/call', { name: 'activity_attempts', arguments: { record_key: 'message:9007199254740991' } }), 'not_found');
  552 |     reader = await approve(page, await startConsent(page, data, 'campaigns.read'), 'Browser owner own');
  553 |     const listed = await mcp(reader, 'tools/list', { per_page: 100 });
  554 |     expect((await listed.json()).result.tools.map(tool => tool.name).sort()).toEqual(['campaigns_get', 'campaigns_list']);
  555 |     const summary = await mcp(reader, 'tools/call', { name: 'campaigns_get', arguments: { campaign_id: created.id } });
  556 |     expect((await summary.json()).result.structuredContent.data.revision).toEqual({ id: review.revision.id, version: review.revision.version, created_at: review.revision.created_at });
  557 |     await safeError(await mcp(reader, 'tools/call', { name: 'campaigns_get', arguments: { campaign_id: created.id, include: { content: true } } }), 'forbidden');
  558 |     await safeError(await mcp(reader, 'tools/call', { name: 'campaigns_pause', arguments: { campaign_id: created.id, expected_version: resumed.version } }), 'forbidden');
  559 |     await page.goto('/settings/connections');
  560 |     await expect(page.getByText('campaigns_resume · succeeded', { exact: false }).first()).toBeVisible();
  561 |     await page.screenshot({ path: testInfo.outputPath('mcp-business-audit.png'), fullPage: true });
  562 |   } finally {
  563 |     // Existing fixture-account teardown owns the campaign and its immutable dependencies.
  564 |     await connection.client.dispose();
  565 |     await reader?.client.dispose();
  566 |   }
  567 | });
  568 | 
```