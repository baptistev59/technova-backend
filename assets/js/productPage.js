const ensureAlpineRegistration = (registerFn) => {
    if (window.Alpine && typeof window.Alpine.data === 'function') {
        registerFn();
        return;
    }

    document.addEventListener('alpine:init', registerFn, { once: true });
};

const variantConfiguratorFactory = (variantsData, productStock) => ({
    configuredProductStock: Number(productStock ?? 0),
    variants: [],
    attributeKeys: [],
    attributes: {},
    availableOptions: {},
    selectedAttributes: {},
    priceBlockEl: null,
    stockMainEl: null,
    basePriceHtml: '',
    baseStockText: '',
    baseStockCount: null,
    currentVariant: null,
    warningMessage: '',
    priceRangeHtml: '',
    formatter: new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }),
    init() {
        this.priceBlockEl = document.querySelector('#main-price-block');
        this.stockMainEl = document.querySelector('#main-stock');
        this.basePriceHtml = this.priceBlockEl ? this.priceBlockEl.innerHTML : '';
        this.baseStockText = this.stockMainEl ? this.stockMainEl.textContent : '';
        try {
            const raw = variantsData ? JSON.parse(variantsData || '[]') : [];
            this.variants = Array.isArray(raw) ? raw.map((variant, index) => this.normalizeVariant(variant, index)) : [];
        } catch (e) {
            this.variants = [];
        }
        this.computePriceRange();
        this.computeBaseStock();
        this.attributes = this.extractAttributes();
        this.attributeKeys = Object.keys(this.attributes);
        const needsSelection = this.attributeKeys.length > 0;
        const baseStock = this.baseStockCount ?? this.configuredProductStock;
        Alpine.store('variantState').setState(
            needsSelection,
            !needsSelection,
            needsSelection ? 'Sélectionne une configuration pour voir le tarif précis.' : '',
            null,
            !needsSelection ? baseStock : null
        );
        this.resetSelection();
        this.updateAvailableOptions();
        this.updateDisplay(null);
    },
    normalizeVariant(variant, index) {
        const metadata = variant.metadata ?? {};
        const priceFormatted = this.formatter.format(Number(variant.price));
        const promoPriceFormatted = variant.promoPrice ? this.formatter.format(Number(variant.promoPrice)) : null;
        const stockText = variant.stock > 0 ? `${variant.stock} en stock` : 'Réapprovisionnement en cours';
        const priceHtml = promoPriceFormatted
            ? `<span class="line-through text-slate-400 text-xs mr-2">${priceFormatted}</span><span class="text-red-600 font-semibold">${promoPriceFormatted}</span>`
            : `<span class="font-semibold text-slate-900">${priceFormatted}</span>`;
        return {
            key: variant.id ?? index,
            metadata,
            price: Number(variant.price),
            promoPrice: variant.promoPrice ? Number(variant.promoPrice) : null,
            priceFormatted,
            promoPriceFormatted,
            stock: variant.stock ?? 0,
            stockText,
            priceHtml,
        };
    },
    extractAttributes() {
        const attrs = {};
        this.variants.forEach((variant) => {
            Object.entries(variant.metadata).forEach(([attribute, value]) => {
                if (!attrs[attribute]) {
                    attrs[attribute] = new Set();
                }
                attrs[attribute].add(value);
            });
        });
        return Object.fromEntries(Object.entries(attrs).map(([key, set]) => [key, Array.from(set)]));
    },
    resetSelection() {
        this.selectedAttributes = {};
        this.attributeKeys.forEach((key) => {
            this.selectedAttributes[key] = '';
        });
    },
    onSelect(attribute) {
        this.updateAvailableOptions(attribute);
        this.updateCurrentVariant();
    },
    updateAvailableOptions() {
        const options = {};
        this.attributeKeys.forEach((attribute) => {
            const filtered = this.attributes[attribute].filter((value) => {
                const candidate = { ...this.selectedAttributes, [attribute]: value };
                return this.variants.some((variant) => this.matchesSelection(variant, candidate));
            });
            options[attribute] = filtered;
            if (this.selectedAttributes[attribute] && !filtered.includes(this.selectedAttributes[attribute])) {
                this.selectedAttributes[attribute] = '';
            }
        });
        this.availableOptions = options;
    },
    matchesSelection(variant, selection) {
        return Object.entries(selection).every(([attribute, value]) => {
            if (!value) {
                return true;
            }
            return variant.metadata[attribute] === value;
        });
    },
    updateCurrentVariant() {
        const incomplete = Object.values(this.selectedAttributes).some((value) => value === '');
        if (incomplete) {
            this.currentVariant = null;
            this.warningMessage = '';
            this.updateDisplay(null);
            if (this.attributeKeys.length > 0) {
                Alpine.store('variantState').setState(true, false, 'Sélectionne une configuration pour voir le tarif précis.', null, null);
            }
            return;
        }
        const found = this.variants.find((variant) => this.matchesSelection(variant, this.selectedAttributes));
        if (found) {
            this.currentVariant = found;
            this.warningMessage = '';
            this.updateDisplay(found);
            Alpine.store('variantState').setState(true, true, '', found.key ?? null, found.stock ?? null);
        } else {
            this.currentVariant = null;
            this.warningMessage = 'Cette combinaison n’est pas disponible.';
            this.updateDisplay(null);
            Alpine.store('variantState').setState(true, false, 'Cette combinaison n’est pas disponible.', null, null);
        }
    },
    updateDisplay(variant) {
        if (this.priceBlockEl) {
            if (variant) {
                if (variant.promoPrice) {
                    this.priceBlockEl.innerHTML = `<div class="flex items-baseline gap-3"><span class="line-through text-slate-400 text-xl">${variant.priceFormatted}</span><p class="tn-product-info__price text-red-600">${variant.promoPriceFormatted}</p></div>`;
                } else {
                    this.priceBlockEl.innerHTML = `<p class="tn-product-info__price">${variant.priceFormatted}</p>`;
                }
            } else if (this.priceRangeHtml) {
                this.priceBlockEl.innerHTML = this.priceRangeHtml;
            } else {
                this.priceBlockEl.innerHTML = this.basePriceHtml;
            }
        }
        if (this.stockMainEl) {
            this.stockMainEl.textContent = variant ? variant.stockText : this.baseStockText;
        }
    },
    computePriceRange() {
        if (!this.variants.length) {
            this.priceRangeHtml = this.basePriceHtml;
            return;
        }
        let min = Infinity;
        let max = -Infinity;
        this.variants.forEach((variant) => {
            const effective = variant.promoPrice ?? variant.price;
            if (effective < min) {
                min = effective;
            }
            if (effective > max) {
                max = effective;
            }
        });
        const minFormatted = this.formatter.format(min);
        const maxFormatted = this.formatter.format(max);
        if (min === max) {
            this.priceRangeHtml = `<p class="tn-product-info__price">${minFormatted}</p>`;
        } else {
            this.priceRangeHtml = `<p class="tn-product-info__price">${minFormatted} – ${maxFormatted}</p>`;
        }
        this.basePriceHtml = this.priceRangeHtml;
        if (this.priceBlockEl) {
            this.priceBlockEl.innerHTML = this.priceRangeHtml;
        }
    },
    computeBaseStock() {
        if (!this.variants.length) {
            this.baseStockCount = this.baseStockCount ?? this.configuredProductStock;
            this.baseStockText = this.baseStockCount > 0 ? `${this.baseStockCount} disponibles` : 'Réapprovisionnement en cours';
            if (this.stockMainEl) {
                this.stockMainEl.textContent = this.baseStockText;
            }
            return;
        }
        const total = this.variants.reduce((acc, variant) => acc + (variant.stock ?? 0), 0);
        this.baseStockCount = total;
        this.baseStockText = total > 0 ? `${total} disponibles` : 'Réapprovisionnement en cours';
        if (!this.currentVariant && this.stockMainEl) {
            this.stockMainEl.textContent = this.baseStockText;
        }
    },
});

const productGalleryFactory = (imagesData, placeholderUrl) => ({
    placeholderUrl: placeholderUrl ?? '/assets/images/product-placeholder.svg',
    images: [],
    currentIndex: 0,
    zoomVisible: false,
    zoomLensStyle: '',
    lightbox: false,
    swiperRetryCount: 0,
    init() {
        try {
            this.images = imagesData
                ? JSON.parse(imagesData || '[]')
                : [];
        } catch (e) {
            this.images = [];
        }
        this.$nextTick(() => {
            const instantiate = () => {
                if (typeof Swiper === 'undefined') {
                    if (this.swiperRetryCount < 5) {
                        this.swiperRetryCount += 1;
                        setTimeout(instantiate, 50);
                    }
                    return;
                }
                new Swiper('.tn-thumbs-swiper', {
                    slidesPerView: 'auto',
                    spaceBetween: 12,
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                });
            };
            instantiate();
        });
    },
    get currentImage() {
        if (!this.images.length) {
            return { url: this.placeholderUrl, alt: 'Visuel par défaut' };
        }
        return this.images[this.currentIndex];
    },
    selectImage(index) {
        if (index >= 0 && index < this.images.length) {
            this.currentIndex = index;
            this.resetZoom();
        }
    },
    onZoom(event) {
        if (!this.zoomVisible || !this.$refs.main) {
            return;
        }
        const rect = this.$refs.main.getBoundingClientRect();
        const lensSize = 150;
        let x = event.clientX - rect.left;
        let y = event.clientY - rect.top;
        x = Math.max(lensSize / 2, Math.min(rect.width - lensSize / 2, x));
        y = Math.max(lensSize / 2, Math.min(rect.height - lensSize / 2, y));
        const percentX = (x / rect.width) * 100;
        const percentY = (y / rect.height) * 100;
        this.zoomLensStyle = `left:${x}px;top:${y}px;background-image:url('${this.currentImage.url}');background-position:${percentX}% ${percentY}%;`;
    },
    resetZoom() {
        this.zoomLensStyle = '';
    },
    openLightbox() {
        this.lightbox = true;
        document.documentElement.classList.add('overflow-hidden');
    },
    closeLightbox() {
        this.lightbox = false;
        document.documentElement.classList.remove('overflow-hidden');
    },
});

const registerVariantStateStore = () => {
    if (!window.Alpine) {
        return;
    }
    if (Alpine.store('variantState')) {
        return;
    }
    Alpine.store('variantState', {
        needsSelection: false,
        ready: true,
        message: '',
        variantId: null,
        stock: null,
        setState(needs, ready, message, variantId = null, stock = null) {
            this.needsSelection = needs;
            this.ready = ready;
            this.message = message ?? '';
            this.variantId = variantId;
            this.stock = stock;
        }
    });
};

const registerVariantConfigurator = () => {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') {
        return;
    }
    registerVariantStateStore();
    if (Alpine.data('variantConfigurator')) {
        return;
    }
    Alpine.data('variantConfigurator', variantConfiguratorFactory);
};

const registerProductGallery = () => {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') {
        return;
    }
    if (Alpine.data('productGallery')) {
        return;
    }
    Alpine.data('productGallery', productGalleryFactory);
};

const bootstrapProductPageAlpine = () => {
    registerVariantConfigurator();
    registerProductGallery();
};

ensureAlpineRegistration(bootstrapProductPageAlpine);

window.variantConfigurator = variantConfiguratorFactory;
window.productGallery = productGalleryFactory;

const bundleComposer = (config) => {
    const formatter = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' });

    return {
        components: [],
        selections: [],
        selectionCounts: {},
        selectionCounter: 0,
        discountPercent: 0,
        modalOpen: false,
        modalHtml: '',
        modalLoading: false,
        activeComponent: null,
        attributeKeys: [],
        selectedAttributes: {},
        availableOptions: {},
        activeVariant: null,
        activeQuantity: 1,
        init() {
            const raw = Array.isArray(config.components) ? config.components : [];
            this.components = raw.map((component) => ({
                ...component,
                variants: Array.isArray(component.variants) ? component.variants : [],
                priceRange: component.priceRange ?? { label: '—' },
            }));
            this.discountPercent = Number(config.discount ?? 0) || 0;
        },
        openConfiguration(componentId) {
            const component = this.components.find((item) => Number(item.id) === Number(componentId));
            if (!component) {
                return;
            }
            this.activeComponent = component;
            this.prepareAttributes(component);
            this.activeQuantity = 1;
            this.updateAvailableOptions();
            this.updateCurrentVariant();
            this.modalOpen = true;
            this.loadModalPreview(component);
        },
        closeModal() {
            this.modalOpen = false;
            this.activeVariant = null;
            this.modalHtml = '';
            this.modalLoading = false;
        },
        prepareAttributes(component) {
            this.attributeKeys = this.collectAttributeKeys(component.variants);
            this.selectedAttributes = {};
            this.attributeKeys.forEach((key) => {
                this.selectedAttributes[key] = '';
            });
            this.availableOptions = this.buildOptionsMap(component.variants, this.attributeKeys);
        },
        loadModalPreview(component) {
            if (!component?.modalUrl) {
                this.modalHtml = '';
                return;
            }
            this.modalLoading = true;
            this.modalHtml = '';
            fetch(component.modalUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((response) => (response.ok ? response.text() : ''))
                .then((html) => {
                    this.modalHtml = html || '';
                })
                .catch(() => {
                    this.modalHtml = '';
                })
                .finally(() => {
                    this.modalLoading = false;
                });
        },
        collectAttributeKeys(variants) {
            const keys = new Set();
            variants.forEach((variant) => {
                const metadata = variant.metadata ?? {};
                Object.keys(metadata).forEach((key) => keys.add(key));
            });
            return Array.from(keys);
        },
        buildOptionsMap(variants, keys) {
            const options = {};
            keys.forEach((key) => {
                const values = new Set();
                variants.forEach((variant) => {
                    const value = variant.metadata?.[key];
                    if (value !== undefined && value !== null) {
                        values.add(String(value));
                    }
                });
                options[key] = Array.from(values);
            });
            return options;
        },
        onAttributeChange(attribute) {
            this.updateAvailableOptions(attribute);
            this.updateCurrentVariant();
        },
        updateAvailableOptions(attributeToSkip = null) {
            this.attributeKeys.forEach((attribute) => {
                const values = new Set();
                this.activeComponent.variants.forEach((variant) => {
                    if (this.matchesSelection(variant, attribute, attributeToSkip)) {
                        const value = variant.metadata?.[attribute];
                        if (value !== undefined && value !== null) {
                            values.add(String(value));
                        }
                    }
                });
                this.availableOptions[attribute] = Array.from(values);
                if (this.selectedAttributes[attribute] && !this.availableOptions[attribute].includes(this.selectedAttributes[attribute])) {
                    if (attribute !== attributeToSkip) {
                        this.selectedAttributes[attribute] = '';
                    }
                }
            });
        },
        matchesSelection(variant, attributeToSkip = null) {
            const metadata = variant.metadata ?? {};
            return this.attributeKeys.every((attribute) => {
                if (attribute === attributeToSkip) {
                    return true;
                }
                const selected = this.selectedAttributes[attribute];
                if (!selected) {
                    return true;
                }
                return String(metadata[attribute] ?? '') === String(selected);
            });
        },
        updateCurrentVariant() {
            this.activeVariant = this.findMatchingVariant();
        },
        findMatchingVariant() {
            if (!this.activeComponent) {
                return null;
            }
            if (!this.activeComponent.variants.length) {
                return this.buildFallbackVariant();
            }
            const isComplete = this.attributeKeys.every((attribute) => !!this.selectedAttributes[attribute]);
            if (!isComplete) {
                return null;
            }
            return this.activeComponent.variants.find((variant) => this.matchesSelection(variant));
        },
        currentVariantReady() {
            if (!this.activeComponent) {
                return false;
            }
            if (this.attributeKeys.length === 0) {
                return true;
            }
            return this.attributeKeys.every((attribute) => !!this.selectedAttributes[attribute]) && this.activeVariant !== null;
        },
        confirmSelection() {
            if (!this.currentVariantReady()) {
                return;
            }
            const variant = this.activeVariant ?? this.buildFallbackVariant();
            if (!variant || !this.activeComponent) {
                return;
            }
            const unitPrice = (variant.promoPrice && variant.promoPrice > 0) ? variant.promoPrice : variant.price;
            const entry = {
                id: ++this.selectionCounter,
                componentId: this.activeComponent.id,
                name: this.activeComponent.name,
                typeLabel: this.activeComponent.typeLabel,
                variantLabel: this.buildVariantLabel(variant),
                variantId: variant.id ?? null,
                price: unitPrice ?? 0,
                stock: variant.stock ?? 0,
                quantity: Number(this.activeQuantity) > 0 ? Number(this.activeQuantity) : 1,
                selectedAttributes: { ...this.selectedAttributes },
            };
            this.selections.push(entry);
            this.incrementSelectionCount(entry.componentId);
            this.closeModal();
        },
        buildVariantLabel(variant) {
            const metadata = variant.metadata ?? {};
            const parts = Object.entries(metadata)
                .filter(([_, value]) => value !== null && value !== undefined && value !== '')
                .map(([key, value]) => `${key} : ${value}`);
            return parts.length ? parts.join(' • ') : 'Configuration par défaut';
        },
        buildFallbackVariant() {
            if (!this.activeComponent) {
                return null;
            }
            return {
                id: null,
                price: this.activeComponent.basePrice ?? 0,
                promoPrice: this.activeComponent.promoPrice !== null ? this.activeComponent.promoPrice : null,
                stock: this.activeComponent.stock ?? 0,
                metadata: {},
            };
        },
        selectionCount(componentId) {
            const key = String(componentId);
            return this.selectionCounts[key] ?? 0;
        },
        clearSelectionsForComponent(componentId) {
            const key = String(componentId);
            if (!this.selectionCounts[key]) {
                return;
            }
            this.selections = this.selections.filter((item) => {
                if (String(item.componentId) === key) {
                    this.decrementSelectionCount(item.componentId);
                    return false;
                }
                return true;
            });
        },
        removeSelection(selectionId) {
            const index = this.selections.findIndex((item) => item.id === selectionId);
            if (index === -1) {
                return;
            }
            const [removed] = this.selections.splice(index, 1);
            if (removed) {
                this.decrementSelectionCount(removed.componentId);
            }
        },
        incrementSelectionCount(componentId) {
            const key = String(componentId);
            this.selectionCounts[key] = (this.selectionCounts[key] ?? 0) + 1;
        },
        decrementSelectionCount(componentId) {
            const key = String(componentId);
            if (!(key in this.selectionCounts)) {
                return;
            }
            this.selectionCounts[key] -= 1;
            if (this.selectionCounts[key] <= 0) {
                delete this.selectionCounts[key];
            }
        },
        formatCurrency(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '—';
            }
            return formatter.format(Number(value));
        },
        subtotal() {
            if (!this.selections.length) {
                return 0;
            }
            return this.selections.reduce((sum, item) => sum + (Number(item.price) || 0) * (Number(item.quantity) || 0), 0);
        },
        discountAmount() {
            if (!this.discountPercent) {
                return 0;
            }
            return this.subtotal() * (this.discountPercent / 100);
        },
        totalPriceLabel() {
            if (!this.selections.length) {
                return '—';
            }
            const subtotal = this.subtotal();
            const total = subtotal - this.discountAmount();
            if (total <= 0) {
                return '—';
            }
            return this.formatCurrency(total);
        },
        discountLabel() {
            const discount = this.discountAmount();
            if (!discount) {
                return '—';
            }
            return this.formatCurrency(discount);
        },
        discountPercentLabel() {
            const value = Number(this.discountPercent) || 0;
            if (!value) {
                return '';
            }
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: value % 1 === 0 ? 0 : 2,
                maximumFractionDigits: 2,
            }).format(value) + ' %';
        },
        activeVariantBasePrice() {
            const variant = this.activeVariant ?? this.buildFallbackVariant();
            return variant?.price ?? 0;
        },
        activeVariantPromoPrice() {
            const variant = this.activeVariant ?? this.buildFallbackVariant();
            const promo = variant?.promoPrice ?? null;
            return promo && Number(promo) > 0 ? Number(promo) : null;
        },
        activeVariantPrice() {
            const variant = this.activeVariant ?? this.buildFallbackVariant();
            if (!variant) {
                return 0;
            }
            const price = (variant.promoPrice && variant.promoPrice > 0) ? variant.promoPrice : variant.price;
            return price ?? 0;
        },
        activeVariantStockText() {
            const variant = this.activeVariant ?? this.buildFallbackVariant();
            if (!variant) {
                return 'Rupture';
            }
            const stock = Number(variant.stock) || 0;
            return stock > 0 ? `${stock} en stock` : 'Rupture';
        },
        selectionKey(selection) {
            const componentId = selection.componentId ?? 'component';
            const variantId = selection.variantId ?? 'base';
            return `${componentId}:${variantId}`;
        },
        uniqueSelectionCount() {
            const keys = new Set();
            this.selections.forEach((selection) => keys.add(this.selectionKey(selection)));
            return keys.size;
        },
        requiredUniqueSelections() {
            const count = this.components.length;
            if (count <= 1) {
                return count;
            }
            return 2;
        },
        canCheckout() {
            if (!this.selections.length) {
                return false;
            }
            return this.uniqueSelectionCount() >= this.requiredUniqueSelections();
        }
    };
};

window.bundleComposer = bundleComposer;
