// Module ES pour les factories Alpine utilisées globalement
export function slugEditor(initialValue = '') {
    return {
        open: false,
        original: initialValue || '',
        slugValue: initialValue || '',
        openModal() { this.open = true; },
        cancel() { this.slugValue = this.original; this.open = false; },
        confirm() { this.original = this.slugValue || this.original; this.open = false; }
    };
}

export function promoPricing(cfg) {
    const { price = null, promo = null, percent = null } = cfg || {};
    return {
        price: null,
        promo: '',
        percent: '',
        init() {
            this.price = this.normalize(price);
            const initialPromo = this.normalize(promo);
            const initialPercent = this.normalize(percent);
            this.promo = initialPromo !== null ? initialPromo.toFixed(2) : '';
            this.percent = initialPercent !== null ? initialPercent.toFixed(2) : '';
        },
        normalize(value) {
            if (value === null || value === undefined || value === '') return null;
            const parsed = parseFloat(value.toString().replace(',', '.'));
            return Number.isNaN(parsed) ? null : parsed;
        },
        updateFrom(source, rawValue = null) {
            if (source === 'price') this.price = this.normalize(rawValue);
            else if (source === 'promo') this.promo = rawValue;
            else if (source === 'percent') this.percent = rawValue;

            const numericPrice = this.normalize(this.price);
            const numericPromo = this.normalize(this.promo);
            const numericPercent = this.normalize(this.percent);

            if (source === 'percent') {
                if (numericPercent !== null && numericPrice !== null && numericPrice > 0) {
                    const computedPromo = numericPrice * (1 - numericPercent / 100);
                    this.promo = computedPromo > 0 ? computedPromo.toFixed(2) : '';
                } else if (numericPercent === null) this.promo = '';
            } else if (source === 'promo') {
                if (numericPromo !== null && numericPrice !== null && numericPrice > 0) {
                    const computedPercent = 100 - ((numericPromo / numericPrice) * 100);
                    this.percent = computedPercent >= 0 ? computedPercent.toFixed(2) : '';
                } else if (numericPromo === null) this.percent = '';
            } else if (source === 'price') {
                if (numericPrice === null || numericPrice <= 0) { this.promo = ''; this.percent = ''; return; }
                if (numericPercent !== null) {
                    const computedPromo = numericPrice * (1 - numericPercent / 100);
                    this.promo = computedPromo > 0 ? computedPromo.toFixed(2) : '';
                } else if (numericPromo !== null) {
                    const computedPercent = 100 - ((numericPromo / numericPrice) * 100);
                    this.percent = computedPercent >= 0 ? computedPercent.toFixed(2) : '';
                }
            }
        }
    };
}

export function productAttributesConfigurator(config) {
    return {
        definitions: [],
        selectedMap: {},
        selectedAttributes: [],
        serialized: '[]',
        query: '',
        showCreateModal: false,
        init() {
            this.definitions = this.parseJson(config.definitions);
            const initial = this.parseJson(config.selected);
            initial.forEach((item) => {
                const attributeId = Number(item.attribute);
                const definition = this.definitionFor(attributeId);
                if (!attributeId || !definition) return;
                const values = Array.isArray(item.values) ? item.values.map((v) => Number(v)) : [];
                this.selectedMap[attributeId] = { values: Array.from(new Set(values)) };
                this.selectedAttributes.push(attributeId);
            });
            this.refresh();
        },
        parseJson(value) {
            if (Array.isArray(value)) return value;
            if (typeof value !== 'string') return [];
            try { return JSON.parse(value || '[]'); } catch (_) { return []; }
        },
        definitionFor(attributeId) {
            const normalized = Number(attributeId);
            if (!normalized) return null;
            return this.definitions.find((d) => Number(d.id) === normalized) || null;
        },
        valuesFor(attributeId) { const def = this.definitionFor(attributeId); return def && Array.isArray(def.values) ? def.values : []; },
        availableDefinitions() {
            const term = this.query.trim().toLowerCase();
            const results = this.definitions.filter((definition) => {
                if (this.selectedMap[definition.id]) return false;
                if (!term) return true;
                const haystack = `${definition.name ?? ''} ${definition.slug ?? ''}`.toLowerCase();
                return haystack.includes(term);
            });
            return results.slice(0, 6);
        },
        onSubmitSearch() { const available = this.availableDefinitions(); if (available.length > 0) { this.addAttribute(available[0].id); this.query = ''; } else { this.showCreateModal = true; } },
        addAttribute(attributeId) { const definition = this.definitionFor(attributeId); if (!definition || this.selectedMap[definition.id]) return; this.selectedMap[definition.id] = { values: [] }; this.selectedAttributes.push(definition.id); this.showCreateModal = false; this.refresh(); },
        removeAttribute(attributeId) { const normalized = Number(attributeId); if (!normalized || !this.selectedMap[normalized]) return; delete this.selectedMap[normalized]; this.selectedAttributes = this.selectedAttributes.filter((id) => Number(id) !== normalized); this.refresh(); },
        ensureEntry(attributeId) { const normalized = Number(attributeId); if (!normalized) return null; if (!this.selectedMap[normalized]) this.selectedMap[normalized] = { values: [] }; return this.selectedMap[normalized]; },
        toggleValue(attributeId, valueId) { const entry = this.ensureEntry(attributeId); const normalized = Number(valueId); if (!entry || !normalized) return; const index = entry.values.indexOf(normalized); if (index === -1) entry.values.push(normalized); else entry.values.splice(index, 1); this.refresh(); },
        isValueSelected(attributeId, valueId) { const entry = this.ensureEntry(attributeId); return entry ? entry.values.includes(Number(valueId)) : false; },
        selectedValues(attributeId) { const entry = this.ensureEntry(attributeId); return entry ? entry.values : []; },
        refresh() { const payload = []; this.selectedAttributes.forEach((attributeId) => { const entry = this.selectedMap[attributeId]; if (!entry) return; const values = Array.from(new Set(entry.values.filter((v) => !!v))); if (values.length > 0) payload.push({ attribute: Number(attributeId), values }); }); this.serialized = JSON.stringify(payload); }
    };
}

export function productBundleConfigurator(config) {
    return {
        candidates: [],
        selectedItems: [],
        selectedMap: {},
        serialized: '[]',
        query: '',
        isGrouped: false,
        typeFieldSelector: config.typeField ?? null,
        init() {
            this.candidates = this.parse(config.candidates);
            const initial = this.parse(config.selected);
            initial.forEach((entry, index) => { const productId = Number(entry.product); if (!productId) return; this.addCandidate(productId, !!entry.required, entry.position ?? index, true); });
            this.refreshSerialized(); this.setupTypeWatcher();
        },
        parse(raw) { if (Array.isArray(raw)) return raw; if (typeof raw !== 'string') return []; try { return JSON.parse(raw || '[]'); } catch (_) { return []; } },
        setupTypeWatcher() { if (!this.typeFieldSelector) { this.isGrouped = true; this.refreshSerialized(); return; } const field = document.querySelector(this.typeFieldSelector); const refresh = () => { this.isGrouped = !field || field.value === 'grouped'; this.refreshSerialized(); }; refresh(); field?.addEventListener('change', refresh); },
        availableCandidates() { const term = this.query.trim().toLowerCase(); return this.candidates.filter((candidate) => { if (!candidate || this.selectedMap[String(candidate.id)]) return false; if (!term) return true; const haystack = `${candidate.name ?? ''} ${candidate.sku ?? ''}`.toLowerCase(); return haystack.includes(term); }); },
        limitedCandidates() { return this.availableCandidates().slice(0, 6); },
        addCandidate(id, required = false, position = null, silent = false) { const candidate = this.candidates.find((item) => Number(item.id) === Number(id)); if (!candidate || this.selectedMap[String(candidate.id)]) return; const entry = { id: Number(candidate.id), name: candidate.name ?? 'Produit', sku: candidate.sku ?? null, typeLabel: candidate.typeLabel ?? '', priceLabel: candidate.priceLabel ?? '—', required: !!required }; if (typeof position === 'number' && position >= 0 && position <= this.selectedItems.length) this.selectedItems.splice(position, 0, entry); else this.selectedItems.push(entry); this.selectedMap[String(entry.id)] = entry; if (!silent) this.refreshSerialized(); },
        removeCandidate(id) { const numeric = Number(id); this.selectedItems = this.selectedItems.filter((item) => Number(item.id) !== numeric); delete this.selectedMap[String(numeric)]; this.refreshSerialized(); },
        toggleRequired(id, value) { const numeric = Number(id); const item = this.selectedItems.find((entry) => Number(entry.id) === numeric); if (!item) return; item.required = !!value; this.refreshSerialized(); },
        onSubmitSearch() { const [first] = this.availableCandidates(); if (first) { this.addCandidate(first.id); this.query = ''; } },
        refreshSerialized() { if (!this.isGrouped) { this.serialized = '[]'; return; } this.selectedItems.forEach((item, index) => item.position = index); const payload = this.selectedItems.map((item, index) => ({ product: item.id, required: !!item.required, position: index })); this.serialized = JSON.stringify(payload); }
    };
}

export function variantAccordion(config) {
    return {
        openVariant: config.initial ?? null,
        toggle(id) { this.openVariant = this.openVariant === id ? null : id; },
        isOpen(id) { return this.openVariant === id; }
    };
}

// Par compatibilité avec les templates qui utilisent window.* ou x-data="window.xxx(...)"
if (typeof window !== 'undefined') {
    window.slugEditor = slugEditor;
    window.promoPricing = promoPricing;
    window.productAttributesConfigurator = productAttributesConfigurator;
    window.productBundleConfigurator = productBundleConfigurator;
    window.variantAccordion = variantAccordion;
}
