<template>
  <div class="mt-8 relative">
    <div class="py-3 flex items-center border-b border-50">
      <div class="px-3">
        Payment methods
      </div>
    </div>

    <div class="overflow-hidden overflow-x-auto relative">
      <table
        cellpadding="0"
        cellspacing="0"
        data-testid="resource-table"
        class="table w-full">
        <thead>
        <tr>
          <th
            v-for="item in columns"
            :key="item.label"
            class="text-left">
              <span class="cursor-pointer inline-flex items-center">
                {{ item.label }}
              </span>
          </th>
        </tr>
        </thead>
        <tbody>
        <tr
          v-for="invoice in invoices"
          :key="invoice.id">
          <td>
              <span class="whitespace-no-wrap text-left">
                {{ invoice.brand || '-' }}
              </span>
          </td>
          <td>
              <span class="whitespace-no-wrap text-left">
                {{ invoice.country }}
              </span>
          </td>
          <td>
              <span class="whitespace-no-wrap text-left">
                {{ invoice.last_4 }}
              </span>
          </td>
          <td>
              <span class="whitespace-no-wrap text-left">
                {{ invoice.expiration }}
              </span>
          </td>
          <td class="text-right">
            <a
              v-if="invoice.link"
              :href="invoice.link"
              class="btn btn-outline text-sm"
              target="_blank"
            >
              Delete
            </a>
          </td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
  export default {
    name: 'PaymentMethodsTable',

    props: {
      invoices: {
        type: Array,
        default: () => [],
      },
    },

    data: () => ({
      columns: [
        { label: 'Brand' },
        { label: 'Country' },
        { label: 'Last 4 Digits' },
        { label: 'Expiration' },
        { label: 'Actions' },
      ],
    }),
  };
</script>

<style scoped></style>
