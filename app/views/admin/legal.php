<?php /** Variables: $regions $region $langs $lang $status $v */
$levels = ['required' => ['Required', 'warn'], 'likely' => ['Usually required', 'warn'], 'recommended' => ['Recommended', '']];
$langNames = ['en' => 'English', 'de' => 'Deutsch'];
?>
<h1>Legal pages</h1>
<p class="muted">Which legal pages a website needs depends on where you live and whether the site earns money. Choose where you are based, read what is expected there, and create draft pages that are already filled with your details.</p>
<div class="box warn"><b>These texts are templates, not legal advice.</b> Read every page and make sure it is true for your site. If the site is commercial, or you are unsure, have a lawyer or a privacy specialist check it. You are responsible for the content of your pages.</div>

<form method="post" action="<?= e(url('/admin/legal')) ?>">
  <?= csrf_field() ?>

  <h2>1. Where are you based?</h2>
  <p class="muted small">Pick the place where you live or run the site. If visitors come from other countries, their local rules can apply too, and the strictest set is usually the European one.</p>
  <?php foreach ($regions as $id => $r): ?>
    <div class="box">
      <label class="check" style="margin:0;align-items:flex-start">
        <input type="radio" name="region" value="<?= e($id) ?>"<?= $id === $region ? ' checked' : '' ?> required style="margin-top:4px">
        <span><b><?= e($r['name']) ?></b><br><span class="muted"><?= e($r['summary']) ?></span></span>
      </label>
      <details style="margin-top:10px"<?= $id === $region ? ' open' : '' ?>>
        <summary class="small"><b>What you need here</b></summary>
        <ul class="steps" style="margin-top:6px">
          <?php foreach ($r['needs'] as [$title, $level, $why]): ?>
            <li style="display:block">
              <b><?= e($title) ?></b> <span class="pill <?= e($levels[$level][1]) ?>"><?= e($levels[$level][0]) ?></span>
              <div class="muted small"><?= e($why) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php foreach ($r['notes'] as $note): ?><p class="small" style="margin:6px 0 0">• <?= e($note) ?></p><?php endforeach; ?>
      </details>
    </div>
  <?php endforeach; ?>

  <h2>2. About the site</h2>
  <div class="box">
    <div class="row2">
      <div>
        <label class="f" for="type" style="margin-top:0">Is the site commercial?</label>
        <select id="type" name="type">
          <option value="commercial"<?= $v['type'] !== 'private' ? ' selected' : '' ?>>Yes, it earns something (sponsors, ads, affiliate links)</option>
          <option value="private"<?= $v['type'] === 'private' ? ' selected' : '' ?>>No, it is purely private</option>
        </select>
      </div>
      <div>
        <label class="f" for="form" style="margin-top:0">Who runs it?</label>
        <select id="form" name="form">
          <option value="person"<?= $v['form'] !== 'company' ? ' selected' : '' ?>>A person (also a sole trader)</option>
          <option value="company"<?= $v['form'] === 'company' ? ' selected' : '' ?>>A company or association</option>
        </select>
      </div>
    </div>
    <?php if ($langs): ?>
    <label class="f" for="lang">Language of the legal texts</label>
    <select id="lang" name="lang">
      <?php foreach ($langs as $code): ?><option value="<?= e($code) ?>"<?= $code === $lang ? ' selected' : '' ?>><?= e($langNames[$code]) ?></option><?php endforeach; ?>
    </select>
    <p class="hint">Available languages depend on the place you choose. Save to refresh the list after changing it.</p>
    <?php else: ?>
    <p class="hint">The language can be chosen after you picked a place and saved once.</p>
    <input type="hidden" name="lang" value="en">
    <?php endif; ?>
    <p class="small" style="margin:12px 0 4px"><b>What does the site do?</b> This decides which paragraphs the privacy policy contains.</p>
    <label class="check"><input type="checkbox" name="f_contact_form" value="1"<?= $v['f_contact_form'] ? ' checked' : '' ?>> People can write to me through the contact form</label>
    <label class="check"><input type="checkbox" name="f_youtube" value="1"<?= $v['f_youtube'] ? ' checked' : '' ?>> It shows YouTube videos</label>
    <label class="check"><input type="checkbox" name="f_sponsors" value="1"<?= $v['f_sponsors'] ? ' checked' : '' ?>> It shows sponsors or partners</label>
  </div>

  <h2>3. Your details</h2>
  <div class="box">
    <p class="muted small" style="margin-top:0">These go into the legal notice and the privacy policy. Empty fields are simply left out.</p>
    <div class="row2">
      <div><label class="f" for="name" style="margin-top:0">Full name, or name of the company *</label><input type="text" id="name" name="name" value="<?= e($v['name']) ?>"></div>
      <div><label class="f" for="responsible" style="margin-top:0">Responsible for the content</label><input type="text" id="responsible" name="responsible" value="<?= e($v['responsible']) ?>" placeholder="Same as the name"></div>
    </div>
    <label class="f" for="street">Street and number *</label>
    <input type="text" id="street" name="street" value="<?= e($v['street']) ?>">
    <div class="row2">
      <div><label class="f" for="city">Postcode and city *</label><input type="text" id="city" name="city" value="<?= e($v['city']) ?>"></div>
      <div><label class="f" for="country">Country</label><input type="text" id="country" name="country" value="<?= e($v['country']) ?>"></div>
    </div>
    <div class="row2">
      <div><label class="f" for="email">Email *</label><input type="email" id="email" name="email" value="<?= e($v['email']) ?>"></div>
      <div><label class="f" for="phone">Phone</label><input type="text" id="phone" name="phone" value="<?= e($v['phone']) ?>"></div>
    </div>
    <div class="row2">
      <div><label class="f" for="register">Company register and number</label><input type="text" id="register" name="register" value="<?= e($v['register']) ?>"></div>
      <div><label class="f" for="vat">VAT identification number</label><input type="text" id="vat" name="vat" value="<?= e($v['vat']) ?>"></div>
    </div>
    <p class="hint">A company register entry and a VAT number are only needed if you have them. In the DACH countries and the UK, businesses must show them.</p>
    <div class="row2">
      <div><label class="f" for="hosting">Hosting provider</label><input type="text" id="hosting" name="hosting" value="<?= e($v['hosting']) ?>" placeholder="Name and city of your host"></div>
      <div><label class="f" for="retention">Keep contact messages for</label><input type="text" id="retention" name="retention" value="<?= e($v['retention']) ?>" placeholder="6 months" maxlength="60"></div>
    </div>
    <label class="f" for="other_services">Other services that handle visitor data</label>
    <textarea id="other_services" name="other_services" rows="3" maxlength="2000"><?= e($v['other_services']) ?></textarea>
    <p class="hint">Optional. For example a newsletter service or a mail provider, with a short sentence what it does and a link to its privacy policy. It is added as its own paragraph.</p>
  </div>

  <h2>4. Create the pages</h2>
  <div class="box">
    <?php if ($region !== '' && $status): ?>
      <p style="margin-top:0"><b>For <?= e($regions[$region]['name']) ?>, these pages will be created:</b></p>
      <ul class="steps">
        <?php foreach ($status as $s): ?>
          <li>
            <span class="tick<?= $s['row'] && $s['row']['status'] === 'published' ? ' done' : '' ?>"></span>
            <span><?= e($s['title']) ?> <span class="muted small">(<code>/<?= e($s['slug']) ?></code>)</span>
              <?php if (!$s['row']): ?><span class="pill">not created yet</span>
              <?php elseif ($s['row']['status'] === 'published'): ?><span class="pill ok">published</span>
              <?php else: ?><span class="pill warn">draft</span><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted" style="margin-top:0">Pick your place above and press <b>Save</b>. The list of pages shows up here.</p>
    <?php endif; ?>
    <label class="check"><input type="checkbox" name="replace" value="1"> Replace pages I created from these templates before. Changes I made to them are lost.</label>
    <p class="hint">Pages you wrote yourself are never touched. New pages start as <b>drafts</b>, so nothing goes public until you publish it.</p>
    <div class="actions">
      <button class="btn primary" type="submit" name="create" value="1">Save and create the draft pages</button>
      <button class="btn" type="submit">Save only</button>
    </div>
  </div>
</form>
