<script setup>
import { ref, watch } from 'vue';
import { searchCards as searchCardsService } from '../services/cardService';

const searchQuery = ref('');
const cards = ref([]);
const loadingCards = ref(false);
let timeoutId = null;

async function loadCards() {
    if (searchQuery.value.trim().length < 3) {
        cards.value = [];
        return;
    }

    loadingCards.value = true;
    cards.value = await searchCardsService(searchQuery.value);
    loadingCards.value = false;
}

watch(searchQuery, () => {
    if (timeoutId) clearTimeout(timeoutId);

    if (searchQuery.value.length < 3) {
        cards.value = [];
        return;
    }

    timeoutId = setTimeout(loadCards, 300);
});
</script>

<template>
    <div>
        <h1>Rechercher une Carte</h1>
        <label><input v-model="searchQuery" type="text" placeholder="Rechercher (min. 3 caractères)..." /></label>
    </div>
    <div class="card-list">
        <div v-if="loadingCards">Loading...</div>
        <div v-else-if="searchQuery.length >= 3 && cards.length === 0">
            Aucune carte trouvée
        </div>
        <div v-else>
            <div class="card-result" v-for="card in cards" :key="card.id">
                <router-link :to="{ name: 'get-card', params: { uuid: card.uuid } }">
                    {{ card.name }} <span>({{ card.uuid }})</span>
                </router-link>
            </div>
        </div>
    </div>
</template>
