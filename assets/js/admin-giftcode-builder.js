(function () {
  'use strict';

  var catalogNode = document.getElementById('gc-reward-catalog');
  if (!catalogNode) return;
  var catalog;
  try { catalog = JSON.parse(catalogNode.textContent || '{}'); } catch (error) { return; }
  var items = Array.isArray(catalog.items) ? catalog.items : [];
  var options = Array.isArray(catalog.options) ? catalog.options : [];
  var dboEnabled = catalog.dbo_enabled === true;
  var itemById = Object.create(null);
  var optionById = Object.create(null);
  items.forEach(function (item) { itemById[String(item.id)] = item; });
  options.forEach(function (option) { optionById[String(option.id)] = option; });

  var builders = [];
  var activeBuilder = null;
  var activeOptionIndex = -1;
  var lastTrigger = null;
  var itemPicker = document.getElementById('gc-item-picker');
  var optionPicker = document.getElementById('gc-option-picker');
  var itemSearch = document.getElementById('gc-item-search');
  var itemType = document.getElementById('gc-item-type');
  var itemGrid = document.getElementById('gc-item-grid');
  var itemResult = document.getElementById('gc-item-result');
  var optionSearch = document.getElementById('gc-option-search');
  var optionGroup = document.getElementById('gc-option-group');
  var optionList = document.getElementById('gc-option-list');
  var optionResult = document.getElementById('gc-option-result');

  function element(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function number(value, fallback) {
    var parsed = Number(value);
    return Number.isFinite(parsed) ? Math.floor(parsed) : fallback;
  }

  function normalize(text) {
    text = String(text || '').toLowerCase();
    if (text.normalize) text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return text.replace(/đ/g, 'd').trim();
  }

  function displayParam(option, param) {
    var value = number(param, 0) * number(option.display_multiplier, 1);
    return value.toLocaleString('vi-VN') + (option.percent ? '%' : '');
  }

  function serializeReward(reward) {
    var custom = Array.isArray(reward.options) ? reward.options.slice().sort(function (a, b) { return a.id - b.id; }) : [];
    if (!custom.length && number(reward.lock, 0) === 0 && number(reward.magic, 0) === 0) {
      return ['DB', reward.item_id, reward.quantity, reward.minutes].join(':');
    }
    return ['DBO', reward.item_id, reward.quantity, reward.minutes, reward.lock, reward.magic,
      custom.map(function (option) { return option.id + '=' + option.param; }).join(';')].join(':');
  }

  function cloneReward(reward) {
    return {
      item_id: number(reward.item_id, 0),
      quantity: Math.max(1, number(reward.quantity, 1)),
      minutes: Math.max(0, number(reward.minutes, 0)),
      lock: number(reward.lock, 0) === 1 ? 1 : 0,
      magic: Math.max(0, Math.min(1, number(reward.magic, 0))),
      options: Array.isArray(reward.options) ? reward.options.map(function (option) {
        return { id: number(option.id, -1), param: Math.max(1, number(option.param, 1)) };
      }).filter(function (option) { return optionById[String(option.id)]; }) : []
    };
  }

  function createImage(item) {
    var visual = element('span', 'gc-item-visual');
    if (!item.image) {
      visual.appendChild(element('b', '', '✦'));
      return visual;
    }
    var image = document.createElement('img');
    image.src = item.image;
    image.alt = '';
    image.loading = 'lazy';
    image.decoding = 'async';
    image.addEventListener('error', function () {
      visual.textContent = '';
      visual.appendChild(element('b', '', '✦'));
    }, { once: true });
    visual.appendChild(image);
    return visual;
  }

  function field(labelText, input) {
    var label = element('label', 'gc-reward-field');
    label.appendChild(element('span', '', labelText));
    label.appendChild(input);
    return label;
  }

  function sync(builder) {
    builder.input.value = JSON.stringify(builder.rewards);
    var optionCount = builder.rewards.reduce(function (total, reward) { return total + reward.options.length; }, 0);
    var sql = builder.rewards.map(serializeReward).join(',');
    builder.root.querySelector('[data-gc-item-count]').textContent = String(builder.rewards.length);
    builder.root.querySelector('[data-gc-option-count]').textContent = String(optionCount);
    var byteCount = new Blob([sql]).size;
    var byteNode = builder.root.querySelector('[data-gc-byte-count]');
    byteNode.textContent = String(byteCount);
    byteNode.parentNode.classList.toggle('is-over', byteCount > builder.capacity);
    builder.root.classList.toggle('has-items', builder.rewards.length > 0);
  }

  function renderBuilder(builder) {
    builder.list.textContent = '';
    builder.rewards.forEach(function (reward, index) {
      var item = itemById[String(reward.item_id)];
      if (!item) return;
      var card = element('article', 'gc-reward-card');
      var top = element('div', 'gc-reward-card-top');
      top.appendChild(createImage(item));
      var identity = element('div', 'gc-reward-identity');
      identity.appendChild(element('span', '', item.type_name + ' · Lv.' + item.level));
      identity.appendChild(element('h4', '', item.name));
      identity.appendChild(element('code', '', '#' + item.id + ' · icon ' + item.icon_id));
      top.appendChild(identity);
      var actions = element('div', 'gc-reward-actions');
      var duplicate = element('button', 'gc-icon-button', 'Nhân bản');
      duplicate.type = 'button';
      duplicate.addEventListener('click', function () {
        if (builder.rewards.length >= 30) return;
        builder.rewards.splice(index + 1, 0, cloneReward(reward));
        renderBuilder(builder);
      });
      var remove = element('button', 'gc-icon-button gc-remove', 'Xóa');
      remove.type = 'button';
      remove.addEventListener('click', function () {
        builder.rewards.splice(index, 1);
        renderBuilder(builder);
      });
      actions.appendChild(duplicate);
      actions.appendChild(remove);
      top.appendChild(actions);
      card.appendChild(top);

      var controls = element('div', 'gc-reward-controls');
      var quantity = document.createElement('input');
      quantity.type = 'number'; quantity.min = '1'; quantity.max = '1000'; quantity.required = true; quantity.value = String(reward.quantity);
      quantity.addEventListener('input', function () { reward.quantity = Math.max(1, Math.min(1000, number(quantity.value, 1))); sync(builder); });
      controls.appendChild(field('Số lượng', quantity));
      var minutes = document.createElement('input');
      minutes.type = 'number'; minutes.min = '0'; minutes.max = '2147483647'; minutes.required = true; minutes.value = String(reward.minutes);
      minutes.addEventListener('input', function () { reward.minutes = Math.max(0, number(minutes.value, 0)); sync(builder); });
      controls.appendChild(field('Thời hạn (phút, 0 = vĩnh viễn)', minutes));
      var magic = document.createElement('select');
      [['0','Ma pháp / mặc định'],['1','Vật lý']].forEach(function (entry) {
        var choice = document.createElement('option'); choice.value = entry[0]; choice.textContent = entry[1]; magic.appendChild(choice);
      });
      magic.value = String(reward.magic); magic.disabled = !dboEnabled;
      magic.addEventListener('change', function () { reward.magic = number(magic.value, 0); sync(builder); });
      controls.appendChild(field('Hệ item', magic));
      var lockLabel = element('label', 'gc-reward-toggle');
      var lock = document.createElement('input'); lock.type = 'checkbox'; lock.checked = reward.lock === 1; lock.disabled = !dboEnabled;
      lock.addEventListener('change', function () { reward.lock = lock.checked ? 1 : 0; sync(builder); });
      lockLabel.appendChild(lock); lockLabel.appendChild(element('span', '', 'Khóa giao dịch'));
      controls.appendChild(lockLabel);
      card.appendChild(controls);

      var defaults = element('div', 'gc-default-options');
      defaults.appendChild(element('b', '', 'Chỉ số mặc định từ data_item'));
      var defaultList = element('div', 'gc-option-chips');
      (item.default_options || []).forEach(function (option) {
        defaultList.appendChild(element('span', '', '#' + option.id + ' ' + option.name + ' ' + Number(option.param).toLocaleString('vi-VN')));
      });
      if (!(item.default_options || []).length) defaultList.appendChild(element('small', '', 'Template không có chỉ số mặc định khác 0.'));
      defaults.appendChild(defaultList);
      card.appendChild(defaults);

      var custom = element('div', 'gc-custom-options');
      var customHead = element('div', 'gc-custom-head');
      customHead.appendChild(element('b', '', 'Option Admin tùy chỉnh'));
      var addOption = element('button', 'gc-add-option', '＋ Thêm option');
      addOption.type = 'button'; addOption.disabled = !dboEnabled;
      addOption.addEventListener('click', function (event) {
        activeBuilder = builder; activeOptionIndex = index; lastTrigger = event.currentTarget;
        optionSearch.value = ''; optionGroup.value = ''; renderOptionPicker(); openPicker(optionPicker, optionSearch);
      });
      customHead.appendChild(addOption); custom.appendChild(customHead);
      var optionRows = element('div', 'gc-option-rows');
      reward.options.forEach(function (selected, optionIndex) {
        var meta = optionById[String(selected.id)];
        if (!meta) return;
        var row = element('div', 'gc-option-row');
        var copy = element('div', 'gc-option-copy');
        copy.appendChild(element('strong', '', meta.name));
        copy.appendChild(element('small', '', '#' + meta.id + ' · ' + meta.group + '[' + meta.index + '] · tối đa ' + Number(meta.max).toLocaleString('vi-VN')));
        row.appendChild(copy);
        var param = document.createElement('input'); param.type = 'number'; param.min = String(meta.min); param.max = String(meta.max); param.required = true; param.value = String(selected.param);
        var preview = element('span', 'gc-param-preview', displayParam(meta, selected.param));
        param.addEventListener('input', function () {
          selected.param = Math.max(meta.min, Math.min(meta.max, number(param.value, meta.min)));
          preview.textContent = displayParam(meta, selected.param); sync(builder);
        });
        row.appendChild(param); row.appendChild(preview);
        var removeOption = element('button', 'gc-option-remove', '×'); removeOption.type = 'button'; removeOption.setAttribute('aria-label', 'Xóa option ' + meta.name);
        removeOption.addEventListener('click', function () { reward.options.splice(optionIndex, 1); renderBuilder(builder); });
        row.appendChild(removeOption); optionRows.appendChild(row);
      });
      if (!reward.options.length) optionRows.appendChild(element('p', 'gc-no-options', dboEnabled ? 'Chưa thêm option; item sẽ dùng bộ chỉ số mặc định.' : 'Bật DBO trên game server để thêm option.'));
      custom.appendChild(optionRows); card.appendChild(custom);
      builder.list.appendChild(card);
    });
    sync(builder);
  }

  function openPicker(picker, focusNode) {
    picker.hidden = false;
    document.body.classList.add('gc-modal-open');
    window.setTimeout(function () { if (focusNode) focusNode.focus(); }, 0);
  }

  function closePicker(picker) {
    picker.hidden = true;
    document.body.classList.remove('gc-modal-open');
    if (lastTrigger) lastTrigger.focus();
  }

  function renderItemPicker() {
    var query = normalize(itemSearch.value).replace(/^#/, '');
    var type = itemType.value;
    var filtered = items.filter(function (item) {
      var matchesType = type === '' || String(item.type) === type;
      var haystack = normalize(item.name + ' ' + item.id + ' #' + item.id);
      return matchesType && (query === '' || haystack.indexOf(query) !== -1);
    });
    itemGrid.textContent = '';
    filtered.slice(0, 180).forEach(function (item) {
      var selected = activeBuilder && activeBuilder.rewards.some(function (reward) { return reward.item_id === item.id; });
      var button = element('button', 'gc-item-choice' + (selected ? ' is-selected' : ''));
      button.type = 'button'; button.disabled = selected;
      button.appendChild(createImage(item));
      var copy = element('span', 'gc-item-choice-copy');
      copy.appendChild(element('small', '', item.type_name + ' · Lv.' + item.level));
      copy.appendChild(element('strong', '', item.name));
      copy.appendChild(element('code', '', '#' + item.id + ' · icon ' + item.icon_id));
      button.appendChild(copy);
      button.appendChild(element('b', '', selected ? 'Đã chọn' : 'Thêm ＋'));
      button.addEventListener('click', function () {
        if (!activeBuilder || activeBuilder.rewards.length >= 30) return;
        activeBuilder.rewards.push({ item_id:item.id, quantity:1, minutes:0, lock:0, magic:0, options:[] });
        renderBuilder(activeBuilder); closePicker(itemPicker);
      });
      itemGrid.appendChild(button);
    });
    itemResult.textContent = filtered.length > 180 ? 'Hiển thị 180/' + filtered.length + ' kết quả — nhập thêm từ khóa để thu hẹp.' : filtered.length + ' vật phẩm phù hợp.';
  }

  function renderOptionPicker() {
    var query = normalize(optionSearch.value).replace(/^#/, '');
    var group = optionGroup.value;
    var selected = activeBuilder && activeBuilder.rewards[activeOptionIndex]
      ? activeBuilder.rewards[activeOptionIndex].options.map(function (option) { return option.id; }) : [];
    var filtered = options.filter(function (option) {
      var haystack = normalize(option.name + ' ' + option.id + ' #' + option.id + ' ' + option.group);
      return (group === '' || option.group === group) && (query === '' || haystack.indexOf(query) !== -1);
    });
    optionList.textContent = '';
    filtered.forEach(function (option) {
      var exists = selected.indexOf(option.id) !== -1;
      var button = element('button', 'gc-option-choice' + (exists ? ' is-selected' : ''));
      button.type = 'button'; button.disabled = exists;
      var copy = element('span'); copy.appendChild(element('strong', '', option.name));
      copy.appendChild(element('small', '', '#' + option.id + ' · ' + option.group + '[' + option.index + ']'));
      button.appendChild(copy);
      button.appendChild(element('b', '', exists ? 'Đã thêm' : 'Param 1–' + Number(option.max).toLocaleString('vi-VN')));
      button.addEventListener('click', function () {
        if (!activeBuilder || !activeBuilder.rewards[activeOptionIndex]) return;
        activeBuilder.rewards[activeOptionIndex].options.push({ id:option.id, param:Math.max(1, option.min) });
        renderBuilder(activeBuilder); closePicker(optionPicker);
      });
      optionList.appendChild(button);
    });
    optionResult.textContent = filtered.length + ' option phù hợp.';
  }

  Array.prototype.slice.call(document.querySelectorAll('[data-gc-builder]')).forEach(function (root) {
    var input = root.querySelector('.gc-reward-json');
    var initial = root.querySelector('.gc-reward-initial');
    var rewards = [];
    try { rewards = JSON.parse(initial.textContent || '[]'); } catch (error) { rewards = []; }
    var builder = {
      root:root, input:input, list:root.querySelector('.gc-selected-items'),
      rewards:Array.isArray(rewards) ? rewards.map(cloneReward).filter(function (reward) { return itemById[String(reward.item_id)]; }) : [],
      capacity:Math.max(1, number(root.getAttribute('data-capacity'), 255))
    };
    builders.push(builder);
    var add = root.querySelector('.gc-add-item');
    if (add) add.addEventListener('click', function (event) {
      activeBuilder = builder; activeOptionIndex = -1; lastTrigger = event.currentTarget;
      itemSearch.value = ''; itemType.value = ''; renderItemPicker(); openPicker(itemPicker, itemSearch);
    });
    var form = root.closest('form');
    if (form) form.addEventListener('submit', function (event) {
      sync(builder);
      var bytes = new Blob([builder.rewards.map(serializeReward).join(',')]).size;
      if (bytes > builder.capacity) {
        event.preventDefault();
        window.alert('Chuỗi phần thưởng vượt giới hạn cột SQL. Hãy chạy migration giftcode_item_options.sql hoặc giảm item/option.');
      }
    });
    renderBuilder(builder);
  });

  var types = Object.create(null);
  items.forEach(function (item) { types[String(item.type)] = item.type_name; });
  Object.keys(types).sort(function (a, b) { return Number(a) - Number(b); }).forEach(function (type) {
    var option = document.createElement('option'); option.value = type; option.textContent = types[type] + ' (' + type + ')'; itemType.appendChild(option);
  });
  if (itemSearch) itemSearch.addEventListener('input', renderItemPicker);
  if (itemType) itemType.addEventListener('change', renderItemPicker);
  if (optionSearch) optionSearch.addEventListener('input', renderOptionPicker);
  if (optionGroup) optionGroup.addEventListener('change', renderOptionPicker);
  Array.prototype.slice.call(document.querySelectorAll('[data-gc-close]')).forEach(function (button) {
    button.addEventListener('click', function () { closePicker(button.closest('.gc-picker')); });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (itemPicker && !itemPicker.hidden) closePicker(itemPicker);
    if (optionPicker && !optionPicker.hidden) closePicker(optionPicker);
  });
})();
